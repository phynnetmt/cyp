<?php

namespace Cypher\Cloud\Monitoring;

class MonitoringSystem
{
    private array $metrics = [];
    private array $alerts = [];
    private array $logs = [];
    private string $dataDir;

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-cloud/monitoring');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }
        $this->load();
    }

    public function recordMetric(string $name, float $value, array $tags = []): void
    {
        $this->metrics[] = [
            'name' => $name,
            'value' => $value,
            'tags' => $tags,
            'timestamp' => time(),
        ];

        if (count($this->metrics) > 100000) {
            array_splice($this->metrics, 0, count($this->metrics) - 50000);
        }

        $this->checkAlerts($name, $value, $tags);
    }

    public function queryMetrics(string $name, string $aggregation = 'avg', int $minutes = 60): array
    {
        $cutoff = time() - ($minutes * 60);
        $relevant = array_filter($this->metrics, fn($m) =>
            $m['name'] === $name && $m['timestamp'] >= $cutoff
        );

        $values = array_column($relevant, 'value');
        if (empty($values)) return ['count' => 0];

        return match ($aggregation) {
            'avg' => ['value' => array_sum($values) / count($values), 'count' => count($values)],
            'sum' => ['value' => array_sum($values), 'count' => count($values)],
            'max' => ['value' => max($values), 'count' => count($values)],
            'min' => ['value' => min($values), 'count' => count($values)],
            'p95' => $this->percentile($values, 95),
            default => ['value' => $values[0], 'count' => count($values)],
        };
    }

    public function createAlert(string $name, string $metric, string $condition, float $threshold, string $channel = 'console'): string
    {
        $id = uniqid('alert_', true);
        $this->alerts[$id] = [
            'id' => $id,
            'name' => $name,
            'metric' => $metric,
            'condition' => $condition,
            'threshold' => $threshold,
            'channel' => $channel,
            'status' => 'active',
            'created_at' => date('c'),
        ];
        $this->save();
        return $id;
    }

    public function listAlerts(): array
    {
        return array_values($this->alerts);
    }

    public function deleteAlert(string $id): void
    {
        unset($this->alerts[$id]);
        $this->save();
    }

    public function appendLog(string $source, string $level, string $message, array $context = []): void
    {
        $this->logs[] = [
            'source' => $source,
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'timestamp' => date('c'),
        ];

        if (count($this->logs) > 10000) {
            array_shift($this->logs);
        }
    }

    public function queryLogs(array $filters = [], int $limit = 100): array
    {
        $results = $this->logs;

        if (!empty($filters['source'])) {
            $results = array_filter($results, fn($l) => $l['source'] === $filters['source']);
        }
        if (!empty($filters['level'])) {
            $results = array_filter($results, fn($l) => $l['level'] === $filters['level']);
        }
        if (!empty($filters['search'])) {
            $results = array_filter($results, fn($l) => str_contains($l['message'], $filters['search']));
        }

        $results = array_slice(array_reverse($results), 0, $limit);
        return array_values($results);
    }

    public function getDashboard(): array
    {
        $recentMetrics = array_slice($this->metrics, -100);
        $metricNames = array_unique(array_column($recentMetrics, 'name'));

        $panels = [];
        foreach ($metricNames as $name) {
            $panels[$name] = $this->queryMetrics($name, 'avg', 60);
        }

        return [
            'metrics' => $panels,
            'alerts' => count($this->alerts),
            'active_alerts' => count(array_filter($this->alerts, fn($a) => $a['status'] === 'active')),
            'recent_logs' => $this->queryLogs([], 10),
        ];
    }

    private function checkAlerts(string $metric, float $value, array $tags): void
    {
        foreach ($this->alerts as $alert) {
            if ($alert['metric'] !== $metric || $alert['status'] !== 'active') continue;

            $triggered = match ($alert['condition']) {
                '>' => $value > $alert['threshold'],
                '<' => $value < $alert['threshold'],
                '>=' => $value >= $alert['threshold'],
                '<=' => $value <= $alert['threshold'],
                '==' => $value === $alert['threshold'],
                default => false,
            };

            if ($triggered) {
                $this->appendLog('alert', 'warning', "Alert '{$alert['name']}' triggered: {$metric} = {$value} ({$alert['condition']} {$alert['threshold']})");
            }
        }
    }

    private function percentile(array $values, int $p): array
    {
        sort($values);
        $idx = (int)ceil($p / 100 * count($values)) - 1;
        return ['value' => $values[max(0, $idx)], 'count' => count($values)];
    }

    private function load(): void
    {
        $file = $this->dataDir . '/monitoring.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->metrics = $data['metrics'] ?? [];
                $this->alerts = $data['alerts'] ?? [];
                $this->logs = $data['logs'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/monitoring.json',
            json_encode(
                ['metrics' => $this->metrics, 'alerts' => $this->alerts, 'logs' => $this->logs],
                JSON_PRETTY_PRINT
            ),
            LOCK_EX
        );
    }
}
