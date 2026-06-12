<?php

namespace Cypher\Cloud\AgentCloud;

class AgentCloudRuntime
{
    private array $clusters = [];
    private array $schedules = [];
    private string $dataDir;

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-cloud/agents');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }
        $this->load();
    }

    public function createCluster(string $name, string $agentType, int $replicas = 1): array
    {
        $id = uniqid('cluster_', true);
        $cluster = [
            'id' => $id,
            'name' => $name,
            'agent_type' => $agentType,
            'replicas' => $replicas,
            'status' => 'active',
            'uptime' => 0,
            'tasks_completed' => 0,
            'created_at' => date('c'),
        ];

        $this->clusters[$id] = $cluster;
        $this->save();

        return $cluster;
    }

    public function scaleCluster(string $id, int $replicas): array
    {
        if (!isset($this->clusters[$id])) {
            throw new AgentCloudException("Cluster not found: {$id}");
        }
        $this->clusters[$id]['replicas'] = $replicas;
        $this->save();
        return $this->clusters[$id];
    }

    public function getCluster(string $id): ?array
    {
        return $this->clusters[$id] ?? null;
    }

    public function listClusters(): array
    {
        return array_values($this->clusters);
    }

    public function deleteCluster(string $id): void
    {
        unset($this->clusters[$id]);
        $this->save();
    }

    public function scheduleAgent(string $clusterId, string $cron, string $task): string
    {
        $id = uniqid('sched_', true);
        $this->schedules[$id] = [
            'id' => $id,
            'cluster_id' => $clusterId,
            'cron' => $cron,
            'task' => $task,
            'status' => 'active',
            'created_at' => date('c'),
        ];
        $this->save();
        return $id;
    }

    public function listSchedules(): array
    {
        return array_values($this->schedules);
    }

    public function deleteSchedule(string $id): void
    {
        unset($this->schedules[$id]);
        $this->save();
    }

    public function getStats(): array
    {
        return [
            'clusters' => count($this->clusters),
            'total_replicas' => array_sum(array_column($this->clusters, 'replicas')),
            'schedules' => count($this->schedules),
        ];
    }

    private function load(): void
    {
        $file = $this->dataDir . '/clusters.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->clusters = $data['clusters'] ?? [];
                $this->schedules = $data['schedules'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/clusters.json',
            json_encode(['clusters' => $this->clusters, 'schedules' => $this->schedules], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
