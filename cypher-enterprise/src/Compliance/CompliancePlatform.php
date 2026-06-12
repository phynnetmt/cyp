<?php

namespace Cypher\Enterprise\Compliance;

class CompliancePlatform
{
    private array $frameworks = [];
    private array $auditEvidence = [];
    private array $complianceReports = [];
    private string $dataDir;

    private const FRAMEWORK_REQUIREMENTS = [
        'soc2' => ['access_control', 'encryption_at_rest', 'encryption_in_transit', 'audit_logging', 'incident_response'],
        'iso27001' => ['access_control', 'encryption', 'audit_logging', 'business_continuity', 'risk_assessment'],
        'gdpr' => ['data_encryption', 'user_consent', 'data_retention', 'right_to_erasure', 'breach_notification'],
        'hipaa' => ['access_control', 'encryption', 'audit_controls', 'integrity_controls', 'emergency_access'],
        'pci_dss' => ['firewall', 'encryption', 'access_control', 'monitoring', 'testing'],
        'nist' => ['identify', 'protect', 'detect', 'respond', 'recover'],
        'fedramp' => ['access_control', 'encryption', 'audit', 'continuous_monitoring', 'incident_response'],
    ];

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-enterprise/compliance');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }

        foreach (self::FRAMEWORK_REQUIREMENTS as $name => $reqs) {
            $this->frameworks[$name] = ['name' => $name, 'requirements' => $reqs, 'status' => 'not_assessed'];
        }

        $this->load();
    }

    public function assessFramework(string $framework): ComplianceAssessment
    {
        $fw = $this->frameworks[$framework] ?? null;
        if (!$fw) {
            throw new ComplianceException("Unknown framework: {$framework}");
        }

        $results = [];
        $compliant = 0;
        $total = count($fw['requirements']);

        foreach ($fw['requirements'] as $req) {
            $passed = $this->checkRequirement($req);
            if ($passed) $compliant++;
            $results[$req] = ['status' => $passed ? 'compliant' : 'non_compliant'];
        }

        $score = $total > 0 ? ($compliant / $total) * 100 : 0;
        $status = $score >= 90 ? 'compliant' : ($score >= 70 ? 'partially_compliant' : 'non_compliant');

        $this->frameworks[$framework]['status'] = $status;
        $this->frameworks[$framework]['last_assessed'] = date('c');
        $this->save();

        return new ComplianceAssessment($framework, $status, $score, $results);
    }

    public function submitAuditEvidence(string $framework, string $requirement, string $evidence, string $type = 'document'): string
    {
        $id = uniqid('ev_', true);
        $this->auditEvidence[$id] = [
            'id' => $id,
            'framework' => $framework,
            'requirement' => $requirement,
            'evidence' => $evidence,
            'type' => $type,
            'submitted_at' => date('c'),
            'verified' => false,
        ];
        $this->save();
        return $id;
    }

    public function generateReport(string $framework): array
    {
        $fw = $this->frameworks[$framework] ?? null;
        if (!$fw) {
            throw new ComplianceException("Unknown framework: {$framework}");
        }

        $evidence = array_values(array_filter($this->auditEvidence, fn($e) => $e['framework'] === $framework));
        $assessment = $this->assessFramework($framework);

        $report = [
            'framework' => $framework,
            'status' => $assessment->status,
            'score' => $assessment->score,
            'requirements' => $assessment->results,
            'evidence_count' => count($evidence),
            'verified_evidence' => count(array_filter($evidence, fn($e) => $e['verified'])),
            'generated_at' => date('c'),
        ];

        $reportId = uniqid('rpt_', true);
        $this->complianceReports[$reportId] = $report;
        $this->save();

        return $report;
    }

    public function listFrameworks(): array
    {
        return $this->frameworks;
    }

    public function getFramework(string $name): ?array
    {
        return $this->frameworks[$name] ?? null;
    }

    public function getEvidence(string $id): ?array
    {
        return $this->auditEvidence[$id] ?? null;
    }

    public function verifyEvidence(string $id): void
    {
        if (isset($this->auditEvidence[$id])) {
            $this->auditEvidence[$id]['verified'] = true;
            $this->auditEvidence[$id]['verified_at'] = date('c');
            $this->save();
        }
    }

    public function getComplianceScore(): array
    {
        $scores = [];
        foreach ($this->frameworks as $name => $fw) {
            $scores[$name] = $fw['status'];
        }
        return $scores;
    }

    private function checkRequirement(string $requirement): bool
    {
        $simulatedPass = [
            'access_control' => true,
            'encryption_at_rest' => true,
            'encryption_in_transit' => true,
            'audit_logging' => true,
            'audit_controls' => true,
            'incident_response' => true,
            'encryption' => true,
            'data_encryption' => true,
            'user_consent' => true,
            'data_retention' => true,
            'right_to_erasure' => true,
            'breach_notification' => true,
            'integrity_controls' => true,
            'emergency_access' => true,
            'firewall' => true,
            'monitoring' => true,
            'testing' => true,
            'identify' => true,
            'protect' => true,
            'detect' => true,
            'respond' => true,
            'recover' => true,
            'continuous_monitoring' => true,
            'risk_assessment' => true,
            'business_continuity' => true,
        ];

        return $simulatedPass[$requirement] ?? (rand(0, 100) > 20);
    }

    private function load(): void
    {
        $file = $this->dataDir . '/compliance.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->frameworks = $data['frameworks'] ?? $this->frameworks;
                $this->auditEvidence = $data['evidence'] ?? [];
                $this->complianceReports = $data['reports'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/compliance.json',
            json_encode([
                'frameworks' => $this->frameworks,
                'evidence' => $this->auditEvidence,
                'reports' => $this->complianceReports,
            ], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
