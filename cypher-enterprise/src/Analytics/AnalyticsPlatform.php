<?php

namespace Cypher\Enterprise\Analytics;

class AnalyticsPlatform
{
    private array $events = [];
    private array $dashboards = [];
    private string $dataDir;

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-enterprise/analytics');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }
        $this->load();
    }

    public function trackEvent(string $type, string $category, array $data = [], string $orgId = ''): void
    {
        $this->events[] = [
            'type' => $type,
            'category' => $category,
            'data' => $data,
            'org_id' => $orgId,
            'timestamp' => date('c'),
        ];

        if (count($this->events) > 50000) {
            array_splice($this->events, 0, 10000);
        }
    }

    public function query(string $type, string $category, string $aggregation = 'count', string $period = 'month'): array
    {
        $cutoff = match ($period) {
            'day' => strtotime('-1 day'),
            'week' => strtotime('-1 week'),
            'month' => strtotime('-1 month'),
            'quarter' => strtotime('-3 months'),
            'year' => strtotime('-1 year'),
            default => strtotime('-1 month'),
        };

        $relevant = array_filter($this->events, fn($e) =>
            $e['type'] === $type && $e['category'] === $category && strtotime($e['timestamp']) >= $cutoff
        );

        $values = array_map(fn($e) => $e['data']['value'] ?? 1, $relevant);

        return match ($aggregation) {
            'count' => ['value' => count($values), 'period' => $period],
            'sum' => ['value' => array_sum($values), 'period' => $period],
            'avg' => ['value' => count($values) > 0 ? array_sum($values) / count($values) : 0, 'period' => $period],
            default => ['value' => count($values), 'period' => $period],
        };
    }

    public function createDashboard(string $name, string $orgId, array $panels = []): array
    {
        $id = uniqid('dash_', true);
        $dashboard = [
            'id' => $id,
            'name' => $name,
            'org_id' => $orgId,
            'panels' => $panels,
            'created_at' => date('c'),
        ];
        $this->dashboards[$id] = $dashboard;
        $this->save();
        return $dashboard;
    }

    public function getDashboard(string $id): ?array
    {
        return $this->dashboards[$id] ?? null;
    }

    public function listDashboards(string $orgId = ''): array
    {
        if ($orgId) {
            return array_values(array_filter($this->dashboards, fn($d) => $d['org_id'] === $orgId));
        }
        return array_values($this->dashboards);
    }

    public function getExecutiveSummary(string $orgId = ''): array
    {
        return [
            'total_applications' => $this->query('deployment', 'application', 'count', 'month'),
            'total_agents' => $this->query('agent', 'execution', 'count', 'month'),
            'active_users' => $this->query('user', 'session', 'count', 'month'),
            'api_requests' => $this->query('api', 'request', 'count', 'month'),
        ];
    }

    private function load(): void
    {
        $file = $this->dataDir . '/analytics.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->events = $data['events'] ?? [];
                $this->dashboards = $data['dashboards'] ?? [];
            }
        }
    }

    private function save(): void
    {
        $data = ['events_since' => date('c'), 'dashboards' => $this->dashboards];
        file_put_contents($this->dataDir . '/analytics.json', json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }
}
