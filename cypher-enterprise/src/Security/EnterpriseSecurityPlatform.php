<?php

namespace Cypher\Enterprise\Security;

class EnterpriseSecurityPlatform
{
    private array $keys = [];
    private array $threatDetections = [];
    private array $vulnerabilityScans = [];
    private string $dataDir;

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-enterprise/security');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }
        $this->load();
    }

    public function generateKey(string $name, string $algorithm = 'aes-256-gcm'): array
    {
        $id = uniqid('key_', true);
        $keyMaterial = match ($algorithm) {
            'aes-256-gcm' => random_bytes(32),
            'rsa-2048' => 'RSA_KEY_GENERATION_REQUIRES_OPENSSL',
            default => random_bytes(32),
        };

        $this->keys[$id] = [
            'id' => $id,
            'name' => $name,
            'algorithm' => $algorithm,
            'created_at' => date('c'),
            'rotated_at' => date('c'),
            'status' => 'active',
        ];
        $this->save();

        return $this->keys[$id];
    }

    public function rotateKey(string $id): array
    {
        if (!isset($this->keys[$id])) {
            throw new SecurityException("Key not found: {$id}");
        }
        $this->keys[$id]['rotated_at'] = date('c');
        $this->keys[$id]['status'] = 'active';
        $this->save();
        return $this->keys[$id];
    }

    public function revokeKey(string $id): void
    {
        if (!isset($this->keys[$id])) {
            throw new SecurityException("Key not found: {$id}");
        }
        $this->keys[$id]['status'] = 'revoked';
        $this->save();
    }

    public function listKeys(): array
    {
        return array_values($this->keys);
    }

    public function recordThreat(string $type, string $severity, string $source, string $description): string
    {
        $id = uniqid('threat_', true);
        $this->threatDetections[$id] = [
            'id' => $id,
            'type' => $type,
            'severity' => $severity,
            'source' => $source,
            'description' => $description,
            'status' => 'open',
            'detected_at' => date('c'),
        ];
        $this->save();
        return $id;
    }

    public function listThreats(?string $status = null): array
    {
        $results = array_values($this->threatDetections);
        if ($status) {
            $results = array_filter($results, fn($t) => $t['status'] === $status);
        }
        return array_values($results);
    }

    public function resolveThreat(string $id): void
    {
        if (isset($this->threatDetections[$id])) {
            $this->threatDetections[$id]['status'] = 'resolved';
            $this->threatDetections[$id]['resolved_at'] = date('c');
            $this->save();
        }
    }

    public function runVulnerabilityScan(string $target, array $options = []): array
    {
        $id = uniqid('scan_', true);
        $findings = $this->simulateScan($target);

        $this->vulnerabilityScans[$id] = [
            'id' => $id,
            'target' => $target,
            'status' => 'completed',
            'findings' => $findings,
            'critical_count' => count(array_filter($findings, fn($f) => $f['severity'] === 'critical')),
            'high_count' => count(array_filter($findings, fn($f) => $f['severity'] === 'high')),
            'medium_count' => count(array_filter($findings, fn($f) => $f['severity'] === 'medium')),
            'scanned_at' => date('c'),
        ];
        $this->save();

        return $this->vulnerabilityScans[$id];
    }

    public function getScanResults(string $id): ?array
    {
        return $this->vulnerabilityScans[$id] ?? null;
    }

    public function getSecurityPosture(): array
    {
        return [
            'active_keys' => count(array_filter($this->keys, fn($k) => $k['status'] === 'active')),
            'open_threats' => count(array_filter($this->threatDetections, fn($t) => $t['status'] === 'open')),
            'scans_completed' => count($this->vulnerabilityScans),
        ];
    }

    private function simulateScan(string $target): array
    {
        $findings = [];

        $checks = [
            ['id' => 'CYP-001', 'title' => 'Outdated dependency detected', 'severity' => 'medium'],
            ['id' => 'CYP-002', 'title' => 'Missing security headers', 'severity' => 'low'],
            ['id' => 'CYP-003', 'title' => 'Environment variables exposure risk', 'severity' => 'high'],
        ];

        foreach ($checks as $check) {
            if (rand(0, 100) > 60) {
                $findings[] = [
                    'id' => $check['id'],
                    'title' => $check['title'],
                    'severity' => $check['severity'],
                    'target' => $target,
                    'remediation' => 'Follow security best practices for ' . $check['title'],
                ];
            }
        }

        return $findings;
    }

    private function load(): void
    {
        $file = $this->dataDir . '/security.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->keys = $data['keys'] ?? [];
                $this->threatDetections = $data['threats'] ?? [];
                $this->vulnerabilityScans = $data['scans'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/security.json',
            json_encode([
                'keys' => $this->keys,
                'threats' => $this->threatDetections,
                'scans' => $this->vulnerabilityScans,
            ], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
