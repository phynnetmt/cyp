<?php

namespace Cypher\Runtime\Agent;

use Cypher\Runtime\Memory\MemoryManager;
use Cypher\Runtime\Reasoning\ReasoningEngine;
use Cypher\Runtime\Tools\ToolRegistry;
use Cypher\Runtime\Knowledge\KnowledgeEngine;

class Agent
{
    private string $id;
    private string $name;
    private string $role;
    private array $config;
    private MemoryManager $memory;
    private ReasoningEngine $reasoning;
    private ToolRegistry $tools;
    private ?KnowledgeEngine $knowledge;
    private array $state = [];
    private array $conversation = [];
    private array $goals = [];
    private bool $running = false;
    private int $maxConversationLength;

    public function __construct(string $name, array $config = [])
    {
        $this->id = uniqid('agent_', true);
        $this->name = $name;
        $this->config = $config;
        $this->role = $config['role'] ?? 'assistant';
        $this->memory = new MemoryManager($config['memory'] ?? []);
        $this->reasoning = new ReasoningEngine($config['reasoning'] ?? []);
        $this->tools = new ToolRegistry($this->memory);
        $this->knowledge = null;
        $this->maxConversationLength = $config['max_conversation'] ?? 100;

        $this->initialize();
    }

    public function initialize(): void
    {
        $this->state = [
            'status' => 'initialized',
            'started_at' => date('c'),
            'tasks_completed' => 0,
            'last_error' => null,
        ];
        $this->running = true;

        $systemPrompt = $this->config['system_prompt']
            ?? "You are {$this->name}, a {$this->role} agent.";
        $this->conversation[] = ['role' => 'system', 'content' => $systemPrompt];
    }

    public function run(string $input, array $context = []): AgentResponse
    {
        if (!$this->running) {
            throw new AgentException("Agent '{$this->name}' is not running");
        }

        $this->conversation[] = ['role' => 'user', 'content' => $input];

        // Retrieve relevant memories from all stores
        $memories = array_merge(
            $this->memory->search($input, 3, 'short_term'),
            $this->memory->search($input, 3, 'long_term'),
        );

        // Retrieve knowledge if available
        $knowledge = [];
        if ($this->knowledge) {
            $knowledge = $this->knowledge->search($input, 3);
        }

        $enrichedInput = $this->enrichInput($input, $memories, $knowledge);

        $reasoningResult = $this->reasoning->reason($enrichedInput, $this->conversation);

        // Execute tool calls with error isolation
        $toolResults = [];
        foreach ($reasoningResult->toolCalls as $call) {
            try {
                $result = $this->tools->execute($call['tool'], $call['arguments']);
                $toolResults[] = ['tool' => $call['tool'], 'result' => $result, 'status' => 'ok'];
                $this->conversation[] = [
                    'role' => 'tool',
                    'tool' => $call['tool'],
                    'content' => json_encode($result),
                ];
            } catch (\Exception $e) {
                $error = ['tool' => $call['tool'], 'error' => $e->getMessage(), 'status' => 'error'];
                $toolResults[] = $error;
                $this->conversation[] = ['role' => 'tool', 'tool' => $call['tool'], 'content' => json_encode($error)];
            }
        }

        $this->memory->store([
            'type' => 'conversation',
            'content' => $input,
            'output' => $reasoningResult->output,
            'timestamp' => time(),
        ]);

        $this->state['tasks_completed']++;

        // Prune conversation if too long
        if (count($this->conversation) > $this->maxConversationLength) {
            $systemMsg = $this->conversation[0];
            $this->conversation = array_slice($this->conversation, -$this->maxConversationLength + 1);
            array_unshift($this->conversation, $systemMsg);
        }

        return new AgentResponse(
            output: $reasoningResult->output,
            reasoning: $reasoningResult->reasoning,
            toolResults: $toolResults,
            confidence: $reasoningResult->confidence,
        );
    }

    public function addTool(string $name, callable $handler, array $schema = []): void
    {
        $this->tools->register($name, $handler, $schema);
    }

    public function setKnowledgeEngine(KnowledgeEngine $engine): void
    {
        $this->knowledge = $engine;
    }

    public function setGoal(string $goal): void
    {
        $this->goals[] = [
            'goal' => $goal,
            'created_at' => time(),
            'status' => 'active',
        ];
    }

    public function getMemory(): MemoryManager
    {
        return $this->memory;
    }

    public function getReasoning(): ReasoningEngine
    {
        return $this->reasoning;
    }

    public function getTools(): ToolRegistry
    {
        return $this->tools;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getState(): array
    {
        return $this->state;
    }

    public function getConversation(): array
    {
        return array_slice($this->conversation, -20);
    }

    public function shutdown(): void
    {
        $this->running = false;
        $this->state['status'] = 'shutdown';
        $this->state['shutdown_at'] = date('c');
    }

    private function enrichInput(string $input, array $memories, array $knowledge): string
    {
        $parts = [$input];

        $validMemories = array_filter($memories, fn($m) => isset($m['content']));
        if (!empty($validMemories)) {
            $memStr = implode("\n", array_map(fn($m) => "- {$m['content']}", $validMemories));
            $parts[] = "\n\nRelevant memories:\n{$memStr}";
        }

        $validKnowledge = array_filter($knowledge, fn($k) => isset($k['content']));
        if (!empty($validKnowledge)) {
            $knowStr = implode("\n", array_map(fn($k) => "- {$k['content']}", $validKnowledge));
            $parts[] = "\n\nKnowledge context:\n{$knowStr}";
        }

        $activeGoals = array_filter($this->goals, fn($g) => ($g['status'] ?? '') === 'active');
        if (!empty($activeGoals)) {
            $goalsStr = implode("\n", array_map(fn($g) => "- {$g['goal']}", $activeGoals));
            $parts[] = "\n\nActive goals:\n{$goalsStr}";
        }

        return implode("\n", $parts);
    }
}
