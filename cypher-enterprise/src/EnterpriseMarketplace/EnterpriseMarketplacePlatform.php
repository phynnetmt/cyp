<?php

namespace Cypher\Enterprise\EnterpriseMarketplace;

class EnterpriseMarketplacePlatform
{
    private array $privateListings = [];
    private array $certifiedComponents = [];
    private string $dataDir;

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-enterprise/marketplace');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }
        $this->load();
    }

    public function publishPrivate(string $name, string $type, string $orgId, string $description, array $metadata = []): array
    {
        $id = uniqid('ep_', true);
        $listing = [
            'id' => $id,
            'name' => $name,
            'type' => $type,
            'org_id' => $orgId,
            'description' => $description,
            'metadata' => $metadata,
            'status' => 'published',
            'downloads' => 0,
            'created_at' => date('c'),
        ];
        $this->privateListings[$id] = $listing;
        $this->save();
        return $listing;
    }

    public function searchPrivate(string $query, string $orgId, string $type = ''): array
    {
        $results = array_filter($this->privateListings, function($l) use ($query, $orgId, $type) {
            if ($l['org_id'] !== $orgId) return false;
            if ($type && $l['type'] !== $type) return false;
            if (!$query) return true;
            return str_contains(strtolower($l['name']), strtolower($query))
                || str_contains(strtolower($l['description']), strtolower($query));
        });
        return array_values($results);
    }

    public function certifyComponent(string $name, string $version, string $orgId, array $checks = []): array
    {
        $id = uniqid('cert_', true);
        $component = [
            'id' => $id,
            'name' => $name,
            'version' => $version,
            'org_id' => $orgId,
            'checks' => $checks,
            'certified_at' => date('c'),
            'status' => 'certified',
        ];
        $this->certifiedComponents[$id] = $component;
        $this->save();
        return $component;
    }

    public function listCertifiedComponents(string $orgId = ''): array
    {
        if ($orgId) {
            return array_values(array_filter($this->certifiedComponents, fn($c) => $c['org_id'] === $orgId));
        }
        return array_values($this->certifiedComponents);
    }

    public function getStats(): array
    {
        return [
            'private_listings' => count($this->privateListings),
            'certified_components' => count($this->certifiedComponents),
        ];
    }

    private function load(): void
    {
        $file = $this->dataDir . '/enterprise_marketplace.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->privateListings = $data['listings'] ?? [];
                $this->certifiedComponents = $data['certified'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/enterprise_marketplace.json',
            json_encode(['listings' => $this->privateListings, 'certified' => $this->certifiedComponents], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
