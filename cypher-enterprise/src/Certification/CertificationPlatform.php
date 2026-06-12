<?php

namespace Cypher\Enterprise\Certification;

class CertificationPlatform
{
    private array $tracks = [];
    private array $certifications = [];
    private string $dataDir;

    private const DEFAULT_TRACKS = [
        'cyp_developer' => [
            'name' => 'CYP Developer',
            'description' => 'Foundational CYP development skills',
            'modules' => ['Language Basics', 'Compiler Usage', 'Package Management', 'Testing'],
            'duration_hours' => 40,
        ],
        'cyp_architect' => [
            'name' => 'CYP Architect',
            'description' => 'Enterprise application architecture with CYP',
            'modules' => ['System Design', 'Microservices', 'Security Architecture', 'Performance'],
            'duration_hours' => 80,
        ],
        'cyp_ai_engineer' => [
            'name' => 'CYP AI Engineer',
            'description' => 'AI agent development and deployment',
            'modules' => ['Agent Development', 'Memory Systems', 'Knowledge Engineering', 'Multi-Agent Systems'],
            'duration_hours' => 60,
        ],
        'cyp_cloud_engineer' => [
            'name' => 'CYP Cloud Engineer',
            'description' => 'Cloud infrastructure and deployment',
            'modules' => ['Deployment', 'Scaling', 'Monitoring', 'Security'],
            'duration_hours' => 50,
        ],
        'cyp_enterprise_architect' => [
            'name' => 'CYP Enterprise Architect',
            'description' => 'Enterprise-wide CYP strategy and governance',
            'modules' => ['Enterprise Architecture', 'Governance', 'Compliance', 'Cost Management'],
            'duration_hours' => 120,
        ],
    ];

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-enterprise/certification');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }

        $this->tracks = self::DEFAULT_TRACKS;
        $this->load();
    }

    public function enroll(string $userId, string $trackName): string
    {
        if (!isset($this->tracks[$trackName])) {
            throw new CertificationException("Unknown track: {$trackName}");
        }

        $id = uniqid('cert_', true);
        $this->certifications[$id] = [
            'id' => $id,
            'user_id' => $userId,
            'track' => $trackName,
            'status' => 'enrolled',
            'progress' => 0.0,
            'modules_completed' => [],
            'enrolled_at' => date('c'),
            'completed_at' => null,
            'score' => null,
        ];
        $this->save();
        return $id;
    }

    public function completeModule(string $certId, string $module, float $score): array
    {
        $cert = $this->certifications[$certId] ?? null;
        if (!$cert) {
            throw new CertificationException("Certification not found");
        }

        $track = $this->tracks[$cert['track']] ?? null;
        if (!$track) {
            throw new CertificationException("Track not found");
        }

        $cert['modules_completed'][] = ['module' => $module, 'score' => $score, 'completed_at' => date('c')];
        $cert['progress'] = count($cert['modules_completed']) / count($track['modules']);

        if ($cert['progress'] >= 1.0) {
            $cert['status'] = 'completed';
            $cert['completed_at'] = date('c');
            $cert['score'] = array_sum(array_column($cert['modules_completed'], 'score')) / count($cert['modules_completed']);
        }

        $this->certifications[$certId] = $cert;
        $this->save();

        return $cert;
    }

    public function getCertification(string $id): ?array
    {
        return $this->certifications[$id] ?? null;
    }

    public function listCertifications(string $userId = ''): array
    {
        if ($userId) {
            return array_values(array_filter($this->certifications, fn($c) => $c['user_id'] === $userId));
        }
        return array_values($this->certifications);
    }

    public function listTracks(): array
    {
        return $this->tracks;
    }

    public function getTrack(string $name): ?array
    {
        return $this->tracks[$name] ?? null;
    }

    public function getStats(): array
    {
        $completed = array_filter($this->certifications, fn($c) => $c['status'] === 'completed');
        return [
            'total_enrolled' => count($this->certifications),
            'total_completed' => count($completed),
            'tracks' => array_keys($this->tracks),
        ];
    }

    private function load(): void
    {
        $file = $this->dataDir . '/certifications.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->certifications = $data['certifications'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/certifications.json',
            json_encode(['certifications' => $this->certifications], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
