<?php

namespace Cypher\Cloud\Marketplace;

class MarketplacePlatform
{
    private array $listings = [];
    private array $downloadLog = [];
    private string $dataDir;

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-cloud/marketplace');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }
        $this->load();
    }

    public function publish(string $name, string $type, string $description, array $metadata = []): array
    {
        foreach ($this->listings as $existing) {
            if ($existing['name'] === $name) {
                throw new \RuntimeException("Listing with name '{$name}' already exists");
            }
        }

        $id = uniqid('mkt_', true);
        $listing = [
            'id' => $id,
            'name' => $name,
            'type' => $type,
            'description' => $description,
            'metadata' => $metadata,
            'status' => 'published',
            'downloads' => 0,
            'rating' => 0.0,
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];

        $this->listings[$id] = $listing;
        $this->save();

        return $listing;
    }

    public function search(string $query, string $type = ''): array
    {
        $results = array_filter($this->listings, function($l) use ($query, $type) {
            if ($type && $l['type'] !== $type) return false;
            if (!$query) return true;
            return str_contains(strtolower($l['name']), strtolower($query))
                || str_contains(strtolower($l['description']), strtolower($query));
        });

        $results = array_values($results);
        usort($results, fn($a, $b) => $b['downloads'] <=> $a['downloads']);
        return $results;
    }

    public function getListing(string $id): ?array
    {
        return $this->listings[$id] ?? null;
    }

    public function recordDownload(string $id, ?string $userId = null): void
    {
        if (!isset($this->listings[$id])) return;

        // Simple dedup: track unique downloaders per listing
        if ($userId !== null) {
            $logKey = "{$id}:{$userId}";
            if (isset($this->downloadLog[$logKey])) return;
            $this->downloadLog[$logKey] = time();
        }

        $this->listings[$id]['downloads']++;
        $this->save();
    }

    public function unpublish(string $id): void
    {
        if (!isset($this->listings[$id])) {
            throw new \RuntimeException("Listing not found: {$id}");
        }
        $this->listings[$id]['status'] = 'unpublished';
        $this->save();
    }

    public function getStats(): array
    {
        $byType = [];
        foreach ($this->listings as $l) {
            $type = $l['type'];
            $byType[$type] = ($byType[$type] ?? 0) + 1;
        }

        return [
            'total' => count($this->listings),
            'by_type' => $byType,
            'total_downloads' => array_sum(array_column($this->listings, 'downloads')),
        ];
    }

    private function load(): void
    {
        $file = $this->dataDir . '/listings.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->listings = $data['listings'] ?? [];
                $this->downloadLog = $data['download_log'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/listings.json',
            json_encode(['listings' => $this->listings, 'download_log' => $this->downloadLog], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
