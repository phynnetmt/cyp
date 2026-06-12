<?php

namespace Cypher\Ecosystem\Partners;

class PartnerEcosystem
{
    private array $partners = [];
    private array $tiers = ['platinum', 'gold', 'silver', 'registered'];
    private string $dataDir;

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-ecosystem/partners');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }
        $this->load();
    }

    public function onboard(string $name, string $type, string $contactEmail, string $tier = 'registered'): array
    {
        if (!in_array($tier, $this->tiers)) {
            throw new PartnerException("Invalid tier: {$tier}");
        }

        $id = uniqid('partner_', true);
        $partner = [
            'id' => $id,
            'name' => $name,
            'type' => $type,
            'contact_email' => $contactEmail,
            'tier' => $tier,
            'status' => 'active',
            'integrations' => [],
            'referrals' => 0,
            'joined_at' => date('c'),
        ];
        $this->partners[$id] = $partner;
        $this->save();
        return $partner;
    }

    public function addIntegration(string $partnerId, string $name, string $description): void
    {
        if (isset($this->partners[$partnerId])) {
            $this->partners[$partnerId]['integrations'][] = [
                'name' => $name,
                'description' => $description,
                'added_at' => date('c'),
            ];
            $this->save();
        }
    }

    public function recordReferral(string $partnerId): void
    {
        if (isset($this->partners[$partnerId])) {
            $this->partners[$partnerId]['referrals']++;
            $this->save();
        }
    }

    public function listPartners(string $tier = '', string $type = ''): array
    {
        $results = $this->partners;
        if ($tier) $results = array_filter($results, fn($p) => $p['tier'] === $tier);
        if ($type) $results = array_filter($results, fn($p) => $p['type'] === $type);
        return array_values($results);
    }

    public function getStats(): array
    {
        return [
            'total' => count($this->partners),
            'by_tier' => array_count_values(array_column($this->partners, 'tier')),
            'by_type' => array_count_values(array_column($this->partners, 'type')),
            'total_integrations' => array_sum(array_map(fn($p) => count($p['integrations']), $this->partners)),
        ];
    }

    public function getPartnerTiers(): array
    {
        return $this->tiers;
    }

    private function load(): void
    {
        $file = $this->dataDir . '/partners.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) $this->partners = $data;
        }
    }

    private function save(): void
    {
        file_put_contents($this->dataDir . '/partners.json', json_encode($this->partners, JSON_PRETTY_PRINT), LOCK_EX);
    }
}
