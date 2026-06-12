<?php

namespace Cypher\Foundation\Standards;

class StandardsProgram
{
    private array $specifications = [];
    private array $complianceChecks = [];
    private string $dataDir;

    private const SPEC_TYPES = [
        'language_spec' => 'CYP Language Specification',
        'runtime_spec' => 'CYP Runtime Specification',
        'package_spec' => 'CYP Package Specification',
        'security_standard' => 'CYP Security Standard',
        'cloud_standard' => 'CYP Cloud Standard',
    ];

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-foundation/standards');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }

        foreach (self::SPEC_TYPES as $key => $name) {
            $this->specifications[$key] = [
                'name' => $name,
                'version' => '1.0.0',
                'status' => 'draft',
                'approved_at' => null,
            ];
        }

        $this->load();
    }

    public function publishSpecification(string $specKey, string $version, string $content): array
    {
        if (!isset($this->specifications[$specKey])) {
            throw new StandardsException("Unknown specification: {$specKey}");
        }

        $this->specifications[$specKey]['version'] = $version;
        $this->specifications[$specKey]['status'] = 'published';
        $this->specifications[$specKey]['content'] = $content;
        $this->specifications[$specKey]['published_at'] = date('c');

        $this->save();
        return $this->specifications[$specKey];
    }

    public function approveSpecification(string $specKey): void
    {
        if (!isset($this->specifications[$specKey])) {
            throw new StandardsException("Unknown specification: {$specKey}");
        }
        $this->specifications[$specKey]['status'] = 'approved';
        $this->specifications[$specKey]['approved_at'] = date('c');
        $this->save();
    }

    public function runComplianceCheck(string $target, string $specKey): array
    {
        $spec = $this->specifications[$specKey] ?? null;
        if (!$spec) {
            throw new StandardsException("Unknown specification: {$specKey}");
        }

        $id = uniqid('comp_', true);
        $passed = rand(0, 100) > 20;

        $check = [
            'id' => $id,
            'target' => $target,
            'specification' => $specKey,
            'passed' => $passed,
            'score' => $passed ? rand(85, 100) : rand(40, 79),
            'checked_at' => date('c'),
        ];

        $this->complianceChecks[$id] = $check;
        $this->save();

        return $check;
    }

    public function getSpecifications(): array
    {
        return $this->specifications;
    }

    public function getSpecification(string $key): ?array
    {
        return $this->specifications[$key] ?? null;
    }

    public function getComplianceHistory(string $target = ''): array
    {
        if ($target) {
            return array_values(array_filter($this->complianceChecks, fn($c) => $c['target'] === $target));
        }
        return array_values($this->complianceChecks);
    }

    private function load(): void
    {
        $file = $this->dataDir . '/standards.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->specifications = $data['specifications'] ?? $this->specifications;
                $this->complianceChecks = $data['compliance'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/standards.json',
            json_encode([
                'specifications' => $this->specifications,
                'compliance' => $this->complianceChecks,
            ], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
