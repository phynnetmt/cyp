<?php

namespace Cypher\Ecosystem\Startups;

class StartupProgram
{
    private array $startups = [];
    private array $credits = [];
    private array $acceleratorPartnerships = [];
    private string $dataDir;

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-ecosystem/startups');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }
        $this->load();
    }

    public function onboard(string $name, string $founderEmail, string $stage, string $description): array
    {
        $id = uniqid('startup_', true);
        $startup = [
            'id' => $id,
            'name' => $name,
            'founder_email' => $founderEmail,
            'stage' => $stage,
            'description' => $description,
            'status' => 'active',
            'credits_remaining' => 1000,
            'support_tier' => 'starter',
            'joined_at' => date('c'),
        ];
        $this->startups[$id] = $startup;
        $this->save();
        return $startup;
    }

    public function grantCredits(string $startupId, float $amount, string $reason): void
    {
        if (!isset($this->startups[$startupId])) {
            throw new StartupException("Startup not found");
        }

        $this->startups[$startupId]['credits_remaining'] += $amount;
        $this->credits[] = [
            'startup_id' => $startupId,
            'amount' => $amount,
            'reason' => $reason,
            'granted_at' => date('c'),
        ];
        $this->save();
    }

    public function useCredits(string $startupId, float $amount): void
    {
        if (!isset($this->startups[$startupId])) {
            throw new StartupException("Startup not found");
        }
        if ($this->startups[$startupId]['credits_remaining'] < $amount) {
            throw new StartupException("Insufficient credits");
        }
        $this->startups[$startupId]['credits_remaining'] -= $amount;
        $this->save();
    }

    public function createAcceleratorPartnership(string $name, string $partnerType, array $benefits): array
    {
        $id = uniqid('accel_', true);
        $partnership = [
            'id' => $id,
            'name' => $name,
            'type' => $partnerType,
            'benefits' => $benefits,
            'status' => 'active',
            'startups_placed' => 0,
            'created_at' => date('c'),
        ];
        $this->acceleratorPartnerships[$id] = $partnership;
        $this->save();
        return $partnership;
    }

    public function referToAccelerator(string $startupId, string $acceleratorId): void
    {
        if (!isset($this->startups[$startupId])) {
            throw new StartupException("Startup not found");
        }
        if (!isset($this->acceleratorPartnerships[$acceleratorId])) {
            throw new StartupException("Accelerator partnership not found");
        }
        $this->acceleratorPartnerships[$acceleratorId]['startups_placed']++;
        $this->save();
    }

    public function listStartups(string $stage = ''): array
    {
        if ($stage) {
            return array_values(array_filter($this->startups, fn($s) => $s['stage'] === $stage));
        }
        return array_values($this->startups);
    }

    public function getStats(): array
    {
        return [
            'total_startups' => count($this->startups),
            'total_credits_granted' => array_sum(array_column($this->credits, 'amount')),
            'accelerator_partners' => count($this->acceleratorPartnerships),
            'startups_placed' => array_sum(array_column($this->acceleratorPartnerships, 'startups_placed')),
        ];
    }

    private function load(): void
    {
        $file = $this->dataDir . '/startups.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->startups = $data['startups'] ?? [];
                $this->credits = $data['credits'] ?? [];
                $this->acceleratorPartnerships = $data['accelerators'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/startups.json',
            json_encode([
                'startups' => $this->startups,
                'credits' => $this->credits,
                'accelerators' => $this->acceleratorPartnerships,
            ], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
