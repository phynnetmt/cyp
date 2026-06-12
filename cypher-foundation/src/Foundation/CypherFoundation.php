<?php

namespace Cypher\Foundation\Foundation;

class CypherFoundation
{
    private array $board = [];
    private array $members = [];
    private array $policies = [];
    private array $financials = [];
    private array $bylaws;
    private string $dataDir;

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-foundation');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }

        $this->bylaws = [
            'name' => 'Cypher Foundation',
            'purpose' => 'Protect, govern, and advance the CYP ecosystem.',
            'principles' => ['Transparency', 'Community Participation', 'Technical Excellence', 'Long-Term Stability', 'Open Collaboration'],
            'version' => '1.0.0',
            'established' => date('c'),
        ];

        $this->load();
    }

    public function appointBoardMember(string $name, string $role, int $termYears = 2): array
    {
        $id = uniqid('board_', true);
        $member = [
            'id' => $id,
            'name' => $name,
            'role' => $role,
            'term_years' => $termYears,
            'status' => 'active',
            'appointed_at' => date('c'),
            'term_end' => date('c', strtotime("+{$termYears} years")),
        ];
        $this->board[$id] = $member;
        $this->save();
        return $member;
    }

    public function addMember(string $name, string $email, string $tier = 'individual', float $contribution = 0): array
    {
        $id = uniqid('mbr_', true);
        $member = [
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'tier' => $tier,
            'contribution' => $contribution,
            'status' => 'active',
            'joined_at' => date('c'),
        ];
        $this->members[$id] = $member;
        $this->save();
        return $member;
    }

    public function createPolicy(string $name, string $category, string $content, string $status = 'draft'): string
    {
        $id = uniqid('pol_', true);
        $this->policies[$id] = [
            'id' => $id,
            'name' => $name,
            'category' => $category,
            'content' => $content,
            'status' => $status,
            'version' => 1,
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];
        $this->save();
        return $id;
    }

    public function approvePolicy(string $policyId): void
    {
        if (isset($this->policies[$policyId])) {
            $this->policies[$policyId]['status'] = 'approved';
            $this->policies[$policyId]['approved_at'] = date('c');
            $this->save();
        }
    }

    public function getBylaws(): array
    {
        return $this->bylaws;
    }

    public function getBoard(): array
    {
        return array_values($this->board);
    }

    public function getMembers(string $tier = ''): array
    {
        if ($tier) {
            return array_values(array_filter($this->members, fn($m) => $m['tier'] === $tier));
        }
        return array_values($this->members);
    }

    public function getPolicies(string $status = ''): array
    {
        if ($status) {
            return array_values(array_filter($this->policies, fn($p) => $p['status'] === $status));
        }
        return array_values($this->policies);
    }

    public function getStats(): array
    {
        return [
            'board_members' => count($this->board),
            'total_members' => count($this->members),
            'policies' => count($this->policies),
            'member_tiers' => array_count_values(array_column($this->members, 'tier')),
            'total_contributions' => array_sum(array_column($this->members, 'contribution')),
        ];
    }

    private function load(): void
    {
        $file = $this->dataDir . '/foundation.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->board = $data['board'] ?? [];
                $this->members = $data['members'] ?? [];
                $this->policies = $data['policies'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/foundation.json',
            json_encode([
                'board' => $this->board,
                'members' => $this->members,
                'policies' => $this->policies,
            ], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
