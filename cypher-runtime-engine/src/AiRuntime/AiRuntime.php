<?php

namespace Cypher\RuntimeEngine\AiRuntime;

class AiRuntime
{
    private array $agents = [];
    private array $embeddings = [];
    private array $workflows = [];

    public function __construct(private array $config = []) {}

    public function createAgent(string $name, string $model = 'default', array $config = []): array
    {
        $id = uniqid('agent_', true);
        $agent = [
            'id' => $id,
            'name' => $name,
            'model' => $model,
            'config' => $config,
            'status' => 'ready',
            'tasks_completed' => 0,
            'created_at' => date('c'),
        ];
        $this->agents[$id] = $agent;
        return $agent;
    }

    public function runAgent(string $agentId, string $input): array
    {
        $agent = $this->agents[$agentId] ?? null;
        if (!$agent) {
            throw new AiRuntimeException("Agent not found: {$agentId}");
        }

        $this->agents[$agentId]['tasks_completed']++;
        $this->agents[$agentId]['last_run'] = date('c');

        return [
            'agent_id' => $agentId,
            'input' => $input,
            'output' => "Processed: {$input}",
            'status' => 'completed',
            'duration_ms' => rand(10, 100),
        ];
    }

    public function createEmbedding(string $text, int $dimensions = 1536): array
    {
        $hash = md5($text);
        $seed = hexdec(substr($hash, 0, 8));
        mt_srand($seed);

        $vector = [];
        for ($i = 0; $i < $dimensions; $i++) {
            $vector[] = (mt_rand() / mt_getrandmax()) * 2 - 1;
        }

        $norm = sqrt(array_sum(array_map(fn($v) => $v * $v, $vector)));
        if ($norm > 0) {
            $vector = array_map(fn($v) => $v / $norm, $vector);
        }

        $id = uniqid('emb_', true);
        $this->embeddings[$id] = [
            'id' => $id,
            'text' => $text,
            'vector' => $vector,
            'dimensions' => $dimensions,
            'created_at' => date('c'),
        ];

        return $this->embeddings[$id];
    }

    public function searchSimilar(string $text, int $limit = 5, int $dimensions = 1536): array
    {
        $queryEmb = $this->createEmbedding($text, $dimensions);
        $results = [];

        foreach ($this->embeddings as $id => $emb) {
            if ($id === $queryEmb['id']) continue;
            $similarity = $this->cosineSimilarity($queryEmb['vector'], $emb['vector']);
            $results[] = ['id' => $id, 'text' => $emb['text'], 'score' => $similarity];
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($results, 0, $limit);
    }

    public function defineWorkflow(string $name, array $steps): array
    {
        $id = uniqid('wf_', true);
        $this->workflows[$id] = [
            'id' => $id,
            'name' => $name,
            'steps' => $steps,
            'status' => 'defined',
            'created_at' => date('c'),
        ];
        return $this->workflows[$id];
    }

    public function executeWorkflow(string $workflowId, array $context = []): array
    {
        $wf = $this->workflows[$workflowId] ?? null;
        if (!$wf) {
            throw new AiRuntimeException("Workflow not found: {$workflowId}");
        }

        $results = [];
        foreach ($wf['steps'] as $step) {
            if ($step['type'] === 'agent') {
                $result = $this->runAgent($step['agent_id'], $step['input'] ?? '');
                $results[] = $result;
            }
        }

        return ['workflow_id' => $workflowId, 'status' => 'completed', 'steps' => $results];
    }

    public function getStats(): array
    {
        return [
            'agents' => count($this->agents),
            'embeddings' => count($this->embeddings),
            'workflows' => count($this->workflows),
            'total_tasks' => array_sum(array_column($this->agents, 'tasks_completed')),
        ];
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0; $na = 0; $nb = 0;
        $len = min(count($a), count($b));
        for ($i = 0; $i < $len; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] * $a[$i];
            $nb += $b[$i] * $b[$i];
        }
        $denom = sqrt($na) * sqrt($nb);
        return $denom === 0.0 ? 0 : $dot / $denom;
    }
}
