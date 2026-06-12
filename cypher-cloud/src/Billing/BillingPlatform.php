<?php

namespace Cypher\Cloud\Billing;

class BillingPlatform
{
    private array $usage = [];
    private string $planName = 'free';

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-cloud/billing');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }

        $this->planName = $config['plan'] ?? 'free';

        $this->plans = [
            'free' => ['deployments' => 3, 'databases' => 1, 'agents' => 2, 'bandwidth_gb' => 5, 'price_monthly' => 0],
            'starter' => ['deployments' => 10, 'databases' => 3, 'agents' => 10, 'bandwidth_gb' => 50, 'price_monthly' => 29],
            'pro' => ['deployments' => 50, 'databases' => 10, 'agents' => 50, 'bandwidth_gb' => 500, 'price_monthly' => 99],
            'enterprise' => ['deployments' => -1, 'databases' => -1, 'agents' => -1, 'bandwidth_gb' => -1, 'price_monthly' => 999],
        ];

        $this->load();
    }

    public function trackUsage(string $metric, float $value, array $tags = []): void
    {
        $plan = $this->getPlan($this->planName);
        if ($plan) {
            $limitKey = match ($metric) {
                'deployment' => 'deployments',
                'database' => 'databases',
                'agent' => 'agents',
                'bandwidth_gb' => 'bandwidth_gb',
                default => null,
            };
            if ($limitKey && isset($plan[$limitKey]) && $plan[$limitKey] >= 0) {
                $currentTotal = $this->getUsage('month')[$metric] ?? 0;
                if (($currentTotal + $value) > $plan[$limitKey]) {
                    throw new \RuntimeException("Plan limit reached for {$metric} (limit: {$plan[$limitKey]})");
                }
            }
        }

        $hour = date('Y-m-d-H');
        if (!isset($this->usage[$hour])) {
            $this->usage[$hour] = [];
        }
        if (!isset($this->usage[$hour][$metric])) {
            $this->usage[$hour][$metric] = 0;
        }
        $this->usage[$hour][$metric] += $value;
        $this->save();
    }

    public function getUsage(string $period = 'month'): array
    {
        $now = time();
        $cutoff = match ($period) {
            'day' => $now - 86400,
            'week' => $now - 604800,
            'month' => $now - 2592000,
            default => $now - 2592000,
        };

        $total = [];
        foreach ($this->usage as $hour => $metrics) {
            $hourTime = \DateTime::createFromFormat('Y-m-d-H', $hour)->getTimestamp();
            if ($hourTime >= $cutoff) {
                foreach ($metrics as $metric => $value) {
                    $total[$metric] = ($total[$metric] ?? 0) + $value;
                }
            }
        }

        return $total;
    }

    public function getPlan(string $name): ?array
    {
        return $this->plans[$name] ?? null;
    }

    public function listPlans(): array
    {
        return $this->plans;
    }

    public function getEstimatedCost(string $planName): float
    {
        $plan = $this->getPlan($planName);
        if (!$plan) return 0;

        $usage = $this->getUsage('month');
        $basePrice = $plan['price_monthly'];

        // Overage calculations
        $overage = 0;
        if ($plan['bandwidth_gb'] > 0) {
            $extraBandwidth = max(0, ($usage['bandwidth_gb'] ?? 0) - $plan['bandwidth_gb']);
            $overage += $extraBandwidth * 0.10;
        }

        return $basePrice + $overage;
    }

    public function getInvoice(): array
    {
        $usage = $this->getUsage('month');
        return [
            'period' => date('Y-m'),
            'plan' => $this->planName,
            'usage' => $usage,
            'estimated_cost' => $this->getEstimatedCost($this->planName),
        ];
    }

    private function load(): void
    {
        $file = $this->dataDir . '/usage.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) $this->usage = $data;
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/usage.json',
            json_encode($this->usage, JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
