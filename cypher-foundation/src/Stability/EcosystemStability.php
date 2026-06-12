<?php

namespace Cypher\Foundation\Stability;

class EcosystemStability
{
    private array $releases = [];
    private array $deprecations = [];
    private array $migrations = [];
    private string $dataDir;

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-foundation/stability');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }
        $this->load();
    }

    public function createRelease(string $version, string $type, string $releaseDate, string $supportEnd): array
    {
        $id = uniqid('rel_', true);
        $release = [
            'id' => $id,
            'version' => $version,
            'type' => $type,
            'release_date' => $releaseDate,
            'support_end' => $supportEnd,
            'status' => 'planned',
            'created_at' => date('c'),
        ];
        $this->releases[$id] = $release;
        $this->save();
        return $release;
    }

    public function publishRelease(string $releaseId): void
    {
        if (isset($this->releases[$releaseId])) {
            $this->releases[$releaseId]['status'] = 'released';
            $this->releases[$releaseId]['published_at'] = date('c');
            $this->save();
        }
    }

    public function deprecateFeature(string $name, string $version, string $replacement, string $removalVersion): array
    {
        $id = uniqid('dep_', true);
        $deprecation = [
            'id' => $id,
            'name' => $name,
            'version' => $version,
            'replacement' => $replacement,
            'removal_version' => $removalVersion,
            'status' => 'deprecated',
            'created_at' => date('c'),
        ];
        $this->deprecations[$id] = $deprecation;
        $this->save();
        return $deprecation;
    }

    public function createMigration(string $name, string $fromVersion, string $toVersion, string $instructions): array
    {
        $id = uniqid('mig_', true);
        $migration = [
            'id' => $id,
            'name' => $name,
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'instructions' => $instructions,
            'status' => 'published',
            'created_at' => date('c'),
        ];
        $this->migrations[$id] = $migration;
        $this->save();
        return $migration;
    }

    public function getActiveLTSVersions(): array
    {
        return array_values(array_filter($this->releases, fn($r) =>
            $r['type'] === 'lts' && $r['status'] === 'released' && $r['support_end'] >= date('Y-m-d')
        ));
    }

    public function getDeprecations(string $version = ''): array
    {
        if ($version) {
            return array_values(array_filter($this->deprecations, fn($d) => $d['version'] === $version));
        }
        return array_values($this->deprecations);
    }

    public function getMigrations(string $fromVersion = ''): array
    {
        if ($fromVersion) {
            return array_values(array_filter($this->migrations, fn($m) => $m['from_version'] === $fromVersion));
        }
        return array_values($this->migrations);
    }

    public function getReleases(string $type = ''): array
    {
        if ($type) {
            return array_values(array_filter($this->releases, fn($r) => $r['type'] === $type));
        }
        return array_values($this->releases);
    }

    private function load(): void
    {
        $file = $this->dataDir . '/stability.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->releases = $data['releases'] ?? [];
                $this->deprecations = $data['deprecations'] ?? [];
                $this->migrations = $data['migrations'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/stability.json',
            json_encode([
                'releases' => $this->releases,
                'deprecations' => $this->deprecations,
                'migrations' => $this->migrations,
            ], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
