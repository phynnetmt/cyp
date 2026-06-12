<?php

namespace Cypher\Foundation\Finance;

class FinancialSustainability
{
    private array $revenue = [];
    private array $expenses = [];
    private array $budgets = [];
    private string $dataDir;

    private const MEMBERSHIP_TIERS = [
        'individual' => ['fee_monthly' => 0, 'fee_yearly' => 0],
        'supporter' => ['fee_monthly' => 10, 'fee_yearly' => 100],
        'professional' => ['fee_monthly' => 25, 'fee_yearly' => 250],
        'enterprise' => ['fee_monthly' => 500, 'fee_yearly' => 5000],
    ];

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-foundation/finance');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }
        $this->load();
    }

    public function recordRevenue(string $source, string $category, float $amount, string $description = ''): void
    {
        $this->revenue[] = [
            'source' => $source,
            'category' => $category,
            'amount' => $amount,
            'description' => $description,
            'recorded_at' => date('c'),
        ];
        $this->save();
    }

    public function recordExpense(string $category, float $amount, string $description): void
    {
        $this->expenses[] = [
            'category' => $category,
            'amount' => $amount,
            'description' => $description,
            'recorded_at' => date('c'),
        ];
        $this->save();
    }

    public function createBudget(string $fiscalYear, string $category, float $allocated): array
    {
        $id = uniqid('budget_', true);
        $budget = [
            'id' => $id,
            'fiscal_year' => $fiscalYear,
            'category' => $category,
            'allocated' => $allocated,
            'spent' => 0.0,
            'remaining' => $allocated,
        ];
        $this->budgets[$id] = $budget;
        $this->save();
        return $budget;
    }

    public function getFinancialStatement(string $period = 'month'): array
    {
        $cutoff = match ($period) {
            'month' => strtotime('-30 days'),
            'quarter' => strtotime('-90 days'),
            'year' => strtotime('-365 days'),
            default => strtotime('-30 days'),
        };

        $recentRevenue = array_filter($this->revenue, fn($r) => strtotime($r['recorded_at']) >= $cutoff);
        $recentExpenses = array_filter($this->expenses, fn($e) => strtotime($e['recorded_at']) >= $cutoff);

        $totalRevenue = array_sum(array_column($recentRevenue, 'amount'));
        $totalExpenses = array_sum(array_column($recentExpenses, 'amount'));

        return [
            'period' => $period,
            'total_revenue' => $totalRevenue,
            'total_expenses' => $totalExpenses,
            'net_income' => $totalRevenue - $totalExpenses,
            'revenue_by_category' => array_sum(array_column($this->revenue, 'amount')),
            'expense_by_category' => array_sum(array_column($this->expenses, 'amount')),
            'runway_months' => $totalExpenses > 0 ? round($totalRevenue / $totalExpenses, 1) : 0,
        ];
    }

    public function getMembershipTiers(): array
    {
        return self::MEMBERSHIP_TIERS;
    }

    public function getStats(): array
    {
        return [
            'total_revenue' => array_sum(array_column($this->revenue, 'amount')),
            'total_expenses' => array_sum(array_column($this->expenses, 'amount')),
            'net' => array_sum(array_column($this->revenue, 'amount')) - array_sum(array_column($this->expenses, 'amount')),
            'revenue_sources' => array_count_values(array_column($this->revenue, 'category')),
            'budget_categories' => count($this->budgets),
        ];
    }

    private function load(): void
    {
        $file = $this->dataDir . '/finance.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->revenue = $data['revenue'] ?? [];
                $this->expenses = $data['expenses'] ?? [];
                $this->budgets = $data['budgets'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/finance.json',
            json_encode([
                'revenue' => $this->revenue,
                'expenses' => $this->expenses,
                'budgets' => $this->budgets,
            ], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
