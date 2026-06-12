<?php

namespace Cypher\Compiler\Registry;

class SecurityAuditor
{
    private array $knownVulnerabilities = [];
    private array $auditResults = [];

    public function __construct()
    {
        $this->loadVulnerabilityDatabase();
    }

    public function audit(array $dependencies): array
    {
        $this->auditResults = [];

        foreach ($dependencies as $name => $version) {
            $result = $this->auditPackage($name, $version);
            if ($result) {
                $this->auditResults[] = $result;
            }
        }

        return $this->auditResults;
    }

    public function auditPackage(string $name, string $version): ?array
    {
        $vulns = $this->knownVulnerabilities[$name] ?? [];
        $matched = [];

        foreach ($vulns as $vuln) {
            if ($this->isAffected($version, $vuln['affected_versions'])) {
                $matched[] = $vuln;
            }
        }

        if (empty($matched)) return null;

        return [
            'package' => $name,
            'version' => $version,
            'vulnerabilities' => $matched,
            'severity' => $this->getHighestSeverity($matched),
        ];
    }

    public function hasIssues(): bool
    {
        return !empty($this->auditResults);
    }

    public function getResults(): array
    {
        return $this->auditResults;
    }

    public function getSummary(): string
    {
        if (empty($this->auditResults)) {
            return "No vulnerabilities found.";
        }

        $critical = 0; $high = 0; $medium = 0; $low = 0;
        foreach ($this->auditResults as $r) {
            match ($r['severity']) {
                'critical' => $critical++,
                'high' => $high++,
                'medium' => $medium++,
                default => $low++,
            };
        }

        $parts = [];
        if ($critical) $parts[] = "{$critical} critical";
        if ($high) $parts[] = "{$high} high";
        if ($medium) $parts[] = "{$medium} medium";
        if ($low) $parts[] = "{$low} low";

        return count($this->auditResults) . " package(s) affected: " . implode(', ', $parts);
    }

    private function isAffected(string $version, array $ranges): bool
    {
        foreach ($ranges as $range) {
            if ($range === '*') return true;
            if (str_contains($range, '>=') && str_contains($range, '<')) {
                preg_match('/>=(\S+)\s+<(\S+)/', $range, $m);
                if (isset($m[1], $m[2]) && version_compare($version, $m[1], '>=') && version_compare($version, $m[2], '<')) {
                    return true;
                }
            } elseif ($version === $range) {
                return true;
            }
        }
        return false;
    }

    private function getHighestSeverity(array $vulns): string
    {
        $order = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
        $highest = 'low';
        foreach ($vulns as $v) {
            $s = $v['severity'] ?? 'low';
            if (($order[$s] ?? 0) > ($order[$highest] ?? 0)) {
                $highest = $s;
            }
        }
        return $highest;
    }

    private function loadVulnerabilityDatabase(): void
    {
        $this->knownVulnerabilities = [
            'cyp/insecure' => [
                [
                    'id' => 'CYP-2024-0001',
                    'title' => 'SQL Injection in query builder',
                    'severity' => 'critical',
                    'affected_versions' => ['>=1.0.0 <1.2.0'],
                    'patched_in' => '1.2.0',
                ],
            ],
            'cyp/deprecated' => [
                [
                    'id' => 'CYP-2024-0002',
                    'title' => 'Unmaintained package',
                    'severity' => 'medium',
                    'affected_versions' => ['*'],
                    'patched_in' => null,
                ],
            ],
        ];
    }
}
