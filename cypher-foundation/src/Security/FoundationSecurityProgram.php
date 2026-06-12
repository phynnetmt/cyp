<?php

namespace Cypher\Foundation\Security;

class FoundationSecurityProgram
{
    private array $incidents = [];
    private array $bountyProgram = [];
    private array $vulnerabilities = [];
    private string $dataDir;

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-foundation/security');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }
        $this->load();
    }

    public function reportIncident(string $type, string $severity, string $description, string $reporter = 'anonymous'): string
    {
        $id = uniqid('inc_', true);
        $this->incidents[$id] = [
            'id' => $id,
            'type' => $type,
            'severity' => $severity,
            'description' => $description,
            'reporter' => $reporter,
            'status' => 'reported',
            'reported_at' => date('c'),
            'resolved_at' => null,
        ];
        $this->save();
        return $id;
    }

    public function resolveIncident(string $incidentId, string $resolution): void
    {
        if (isset($this->incidents[$incidentId])) {
            $this->incidents[$incidentId]['status'] = 'resolved';
            $this->incidents[$incidentId]['resolution'] = $resolution;
            $this->incidents[$incidentId]['resolved_at'] = date('c');
            $this->save();
        }
    }

    public function submitBountyReport(string $researcher, string $package, string $vulnerability, string $description): string
    {
        $id = uniqid('bty_', true);
        $this->bountyProgram[$id] = [
            'id' => $id,
            'researcher' => $researcher,
            'package' => $package,
            'vulnerability' => $vulnerability,
            'description' => $description,
            'status' => 'submitted',
            'reward' => 0,
            'submitted_at' => date('c'),
        ];
        $this->save();
        return $id;
    }

    public function reviewBounty(string $bountyId, string $severity, float $reward): void
    {
        if (isset($this->bountyProgram[$bountyId])) {
            $this->bountyProgram[$bountyId]['status'] = 'reviewed';
            $this->bountyProgram[$bountyId]['severity'] = $severity;
            $this->bountyProgram[$bountyId]['reward'] = $reward;
            $this->bountyProgram[$bountyId]['reviewed_at'] = date('c');
            $this->save();
        }
    }

    public function registerVulnerability(string $cve, string $package, string $version, string $description, string $severity): string
    {
        $id = uniqid('vuln_', true);
        $this->vulnerabilities[$id] = [
            'id' => $id,
            'cve' => $cve,
            'package' => $package,
            'version' => $version,
            'description' => $description,
            'severity' => $severity,
            'status' => 'identified',
            'disclosed_at' => null,
            'patched_at' => null,
        ];
        $this->save();
        return $id;
    }

    public function discloseVulnerability(string $vulnerabilityId): void
    {
        if (isset($this->vulnerabilities[$vulnerabilityId])) {
            $this->vulnerabilities[$vulnerabilityId]['status'] = 'disclosed';
            $this->vulnerabilities[$vulnerabilityId]['disclosed_at'] = date('c');
            $this->save();
        }
    }

    public function patchVulnerability(string $vulnerabilityId): void
    {
        if (isset($this->vulnerabilities[$vulnerabilityId])) {
            $this->vulnerabilities[$vulnerabilityId]['status'] = 'patched';
            $this->vulnerabilities[$vulnerabilityId]['patched_at'] = date('c');
            $this->save();
        }
    }

    public function getIncidents(string $status = ''): array
    {
        if ($status) {
            return array_values(array_filter($this->incidents, fn($i) => $i['status'] === $status));
        }
        return array_values($this->incidents);
    }

    public function getVulnerabilities(string $status = ''): array
    {
        if ($status) {
            return array_values(array_filter($this->vulnerabilities, fn($v) => $v['status'] === $status));
        }
        return array_values($this->vulnerabilities);
    }

    public function getBountyReports(string $status = ''): array
    {
        if ($status) {
            return array_values(array_filter($this->bountyProgram, fn($b) => $b['status'] === $status));
        }
        return array_values($this->bountyProgram);
    }

    public function getStats(): array
    {
        return [
            'total_incidents' => count($this->incidents),
            'open_incidents' => count(array_filter($this->incidents, fn($i) => $i['status'] === 'reported')),
            'vulnerabilities' => count($this->vulnerabilities),
            'bounty_reports' => count($this->bountyProgram),
            'total_bounties_paid' => array_sum(array_column($this->bountyProgram, 'reward')),
        ];
    }

    private function load(): void
    {
        $file = $this->dataDir . '/security.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->incidents = $data['incidents'] ?? [];
                $this->bountyProgram = $data['bounties'] ?? [];
                $this->vulnerabilities = $data['vulnerabilities'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/security.json',
            json_encode([
                'incidents' => $this->incidents,
                'bounties' => $this->bountyProgram,
                'vulnerabilities' => $this->vulnerabilities,
            ], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
