<?php

namespace Cypher\Enterprise\CostManagement;

class CostManagementPlatform
{
    private array $budgets = [];
    private array $usageRecords = [];
    private string $dataDir;

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-enterprise/cost');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }
        $this->load();
    }

    public function createBudget(string $name, string $departmentId, float $limit, string $period = 'monthly'): string
    {
        $id = uniqid('budget_', true);
        $this->budgets[$id] = [
            'id' => $id,
            'name' => $name,
            'department_id' => $departmentId,
            'limit' => $limit,
            'period' => $period,
            'spent' => 0.0,
            'status' => 'active',
            'created_at' => date('c'),
        ];
        $this->save();
        return $id;
    }

    public function trackUsage(string $departmentId, string $resource, float $cost, array $tags = []): void
    {
        $this->usageRecords[] = [
            'department_id' => $departmentId,
            'resource' => $resource,
            'cost' => $cost,
            'tags' => $tags,
            'timestamp' => date('c'),
        ];

        // Update budget spending
        foreach ($this->budgets as &$budget) {
            if ($budget['department_id'] === $departmentId && $budget['status'] === 'active') {
                $budget['spent'] += $cost;
                if ($budget['spent'] > $budget['limit']) {
                    $budget['status'] = 'exceeded';
                }
            }
        }

        $this->save();
    }

    public function getDepartmentCosts(string $departmentId, string $period = 'month'): array
    {
        $cutoff = match ($period) {
            'day' => time() - 86400,
            'week' => time() - 604800,
            'month' => time() - 2592000,
            'quarter' => time() - 7776000,
            default => time() - 2592000,
        };

        $total = 0.0;
        $byResource = [];

        foreach ($this->usageRecords as $record) {
            $recordTime = strtotime($record['timestamp']);
            if ($record['department_id'] === $departmentId && $recordTime >= $cutoff) {
                $total += $record['cost'];
                $resource = $record['resource'];
                $byResource[$resource] = ($byResource[$resource] ?? 0) + $record['cost'];
            }
        }

        return [
            'department_id' => $departmentId,
            'period' => $period,
            'total' => $total,
            'by_resource' => $byResource,
        ];
    }

    public function getBudgets(string $departmentId = ''): array
    {
        if ($departmentId) {
            return array_values(array_filter($this->budgets, fn($b) => $b['department_id'] === $departmentId));
        }
        return array_values($this->budgets);
    }

    public function getForecast(string $departmentId): array
    {
        $costs = $this->getDepartmentCosts($departmentId, 'month');
        $dailyRate = $costs['total'] / 30;
        return [
            'department_id' => $departmentId,
            'monthly_total' => $costs['total'],
            'daily_average' => round($dailyRate, 2),
            'projected_monthly' => round($dailyRate * 30, 2),
            'projected_quarterly' => round($dailyRate * 90, 2),
            'projected_annual' => round($dailyRate * 365, 2),
        ];
    }

    public function getCostAllocation(string $orgId): array
    {
        $departmentCosts = [];
        foreach ($this->usageRecords as $record) {
            $dept = $record['department_id'];
            $departmentCosts[$dept] = ($departmentCosts[$dept] ?? 0) + $record['cost'];
        }

        return [
            'total' => array_sum($departmentCosts),
            'by_department' => $departmentCosts,
        ];
    }

    public function getOptimizationRecommendations(): array
    {
        return [
            ['resource' => 'compute', 'recommendation' => 'Rightsize underutilized agent clusters', 'savings_estimate' => 250],
            ['resource' => 'storage', 'recommendation' => 'Enable data lifecycle policies', 'savings_estimate' => 100],
            ['resource' => 'bandwidth', 'recommendation' => 'Enable caching layer', 'savings_estimate' => 75],
        ];
    }

    private function load(): void
    {
        $file = $this->dataDir . '/cost.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->budgets = $data['budgets'] ?? [];
                $this->usageRecords = $data['usage'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/cost.json',
            json_encode(['budgets' => $this->budgets, 'usage' => $this->usageRecords], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
