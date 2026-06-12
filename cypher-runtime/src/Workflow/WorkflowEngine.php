<?php

namespace Cypher\Runtime\Workflow;

use Cypher\Runtime\Agent\AgentRuntime;

class WorkflowEngine
{
    private array $workflows = [];
    private array $schedules = [];
    private array $running = [];
    private AgentRuntime $agentRuntime;

    public function __construct(?AgentRuntime $agentRuntime = null)
    {
        $this->agentRuntime = $agentRuntime ?? new AgentRuntime();
    }

    public function define(string $name, array $steps = []): WorkflowDefinition
    {
        $workflow = new WorkflowDefinition($name, $steps);
        $this->workflows[$name] = $workflow;
        return $workflow;
    }

    public function execute(string $name, array $context = []): WorkflowResult
    {
        $workflow = $this->workflows[$name] ?? null;
        if (!$workflow) {
            throw new WorkflowException("Workflow '{$name}' not found");
        }

        $executionId = uniqid('wf_', true);
        $this->running[$executionId] = ['name' => $name, 'status' => 'running', 'started_at' => time()];

        $results = [];
        $currentContext = $context;
        $steps = $workflow->getSteps();

        try {
            $i = 0;
            while ($i < count($steps)) {
                $step = $steps[$i];
                $stepResult = $this->executeStep($step, $currentContext);
                $results[] = $stepResult;

                if ($stepResult['status'] === 'failed' && !($step['continue_on_error'] ?? false)) {
                    $this->running[$executionId]['status'] = 'failed';
                    return new WorkflowResult($executionId, $name, $results, 'failed');
                }

                $currentContext = array_merge($currentContext, $stepResult['output'] ?? []);

                // Handle conditional branching
                if ($step['type'] === 'condition') {
                    $conditionMet = $stepResult['output']['condition_met'] ?? false;
                    $jumpToId = $conditionMet ? ($step['if_true'] ?? null) : ($step['if_false'] ?? null);
                    if ($jumpToId !== null) {
                        $jumpIdx = null;
                        foreach ($steps as $j => $s) {
                            if (($s['id'] ?? '') === $jumpToId) {
                                $jumpIdx = $j;
                                break;
                            }
                        }
                        if ($jumpIdx === null) {
                            throw new WorkflowException("Condition target step '{$jumpToId}' not found in workflow '{$name}'");
                        }
                        $i = $jumpIdx;
                        continue;
                    }
                }

                $i++;
            }

            $this->running[$executionId]['status'] = 'completed';
            return new WorkflowResult($executionId, $name, $results, 'completed');
        } catch (\Exception $e) {
            $this->running[$executionId]['status'] = 'failed';
            return new WorkflowResult($executionId, $name, $results, 'failed', $e->getMessage());
        }
    }

    public function schedule(string $workflowName, string $cronExpression, array $context = []): string
    {
        $scheduleId = uniqid('sched_', true);
        $this->schedules[$scheduleId] = [
            'workflow' => $workflowName,
            'cron' => $cronExpression,
            'context' => $context,
            'created_at' => time(),
        ];
        return $scheduleId;
    }

    public function listSchedules(): array
    {
        return $this->schedules;
    }

    public function cancelSchedule(string $id): void
    {
        unset($this->schedules[$id]);
    }

    public function getRunningExecutions(): array
    {
        return $this->running;
    }

    public function getWorkflows(): array
    {
        return $this->workflows;
    }

    public function setAgentRuntime(AgentRuntime $runtime): void
    {
        $this->agentRuntime = $runtime;
    }

    private function executeStep(array $step, array $context): array
    {
        return match ($step['type']) {
            'agent' => $this->executeAgentStep($step, $context),
            'action' => $this->executeActionStep($step, $context),
            'condition' => $this->executeConditionStep($step, $context),
            'delay' => $this->executeDelayStep($step),
            'parallel' => $this->executeParallelStep($step, $context),
            default => ['status' => 'failed', 'error' => "Unknown step type: {$step['type']}"],
        };
    }

    private function executeAgentStep(array $step, array $context): array
    {
        try {
            $agentName = $step['agent'] ?? 'default';
            $input = $this->interpolate($step['input'] ?? '', $context);
            $response = $this->agentRuntime->runAgent($agentName, $input);
            return [
                'status' => 'completed',
                'output' => ['response' => $response->output, 'reasoning' => $response->reasoning],
            ];
        } catch (\Exception $e) {
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    private function executeActionStep(array $step, array $context): array
    {
        $action = $step['action'] ?? '';
        $params = $this->interpolateArray($step['params'] ?? [], $context);
        return [
            'status' => 'completed',
            'output' => ['action' => $action, 'params' => $params, 'result' => 'ok'],
        ];
    }

    private function executeConditionStep(array $step, array $context): array
    {
        $condition = $step['condition'] ?? '';
        $evaluated = $this->evaluateCondition($condition, $context);
        return [
            'status' => 'completed',
            'output' => ['condition_met' => $evaluated],
        ];
    }

    private function executeDelayStep(array $step): array
    {
        $seconds = $step['seconds'] ?? 1;
        if ($seconds > 0) {
            usleep(min($seconds, 300) * 1000000);
        }
        return ['status' => 'completed', 'output' => []];
    }

    private function executeParallelStep(array $step, array $context): array
    {
        $results = [];
        foreach ($step['steps'] ?? [] as $subStep) {
            $results[] = $this->executeStep($subStep, $context);
        }
        return ['status' => 'completed', 'output' => ['parallel_results' => $results]];
    }

    private function interpolate(string $text, array $context): string
    {
        return preg_replace_callback('/\{\{(\w+)\}\}/', fn($m) => (string)($context[$m[1]] ?? $m[0]), $text);
    }

    private function interpolateArray(array $data, array $context): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $result[$key] = is_string($value) ? $this->interpolate($value, $context) : $value;
        }
        return $result;
    }

    private function evaluateCondition(string $condition, array $context): bool
    {
        $condition = trim($condition);

        // Check for >= operator first (before > alone)
        if (preg_match('/^(\w+)\s*>=\s*(.+)$/', $condition, $m)) {
            $key = $m[1];
            $val = trim($m[2]);
            $actual = $context[$key] ?? 0;
            return (float)$actual >= (float)$val;
        }

        if (preg_match('/^(\w+)\s*<=\s*(.+)$/', $condition, $m)) {
            $key = $m[1];
            $val = trim($m[2]);
            $actual = $context[$key] ?? 0;
            return (float)$actual <= (float)$val;
        }

        if (preg_match('/^(\w+)\s*!=\s*(.+)$/', $condition, $m)) {
            $key = $m[1];
            $val = trim($m[2]);
            $actual = $context[$key] ?? '';
            return (string)$actual !== (string)$val;
        }

        if (preg_match('/^(\w+)\s*==\s*(.+)$/', $condition, $m)) {
            $key = $m[1];
            $val = trim($m[2]);
            $actual = $context[$key] ?? '';
            return (string)$actual === (string)$val;
        }

        if (preg_match('/^(\w+)\s*>\s*(.+)$/', $condition, $m)) {
            $key = $m[1];
            $val = trim($m[2]);
            $actual = $context[$key] ?? 0;
            return (float)$actual > (float)$val;
        }

        if (preg_match('/^(\w+)\s*<\s*(.+)$/', $condition, $m)) {
            $key = $m[1];
            $val = trim($m[2]);
            $actual = $context[$key] ?? 0;
            return (float)$actual < (float)$val;
        }

        // Bare key: truthy check
        return !empty($context[$condition]);
    }
}
