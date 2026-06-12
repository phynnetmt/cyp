<?php

namespace Cypher\Foundation\Certification;

class CertificationAuthority
{
    private array $programs = [];
    private array $certifications = [];
    private array $exams = [];
    private string $dataDir;

    private const CERT_PROGRAMS = [
        'developer' => [
            'name' => 'CYP Developer Certification',
            'description' => 'Validates foundational CYP development skills',
            'levels' => ['associate', 'professional', 'expert'],
            'topics' => ['Language Basics', 'Compiler Usage', 'Package Management', 'Testing'],
            'pass_threshold' => 70,
            'validity_years' => 2,
        ],
        'architect' => [
            'name' => 'CYP Architect Certification',
            'description' => 'Validates enterprise architecture skills',
            'levels' => ['professional', 'expert'],
            'topics' => ['System Design', 'Security Architecture', 'Performance', 'Scalability'],
            'pass_threshold' => 75,
            'validity_years' => 3,
        ],
        'enterprise' => [
            'name' => 'CYP Enterprise Certification',
            'description' => 'Validates enterprise platform skills',
            'levels' => ['professional', 'expert'],
            'topics' => ['Governance', 'Compliance', 'Cost Management', 'Multi-Tenancy'],
            'pass_threshold' => 80,
            'validity_years' => 3,
        ],
        'instructor' => [
            'name' => 'CYP Instructor Certification',
            'description' => 'Validates teaching and mentoring abilities',
            'levels' => ['professional'],
            'topics' => ['Curriculum Design', 'Teaching Methods', 'Assessment', 'Mentoring'],
            'pass_threshold' => 85,
            'validity_years' => 2,
        ],
    ];

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-foundation/certification');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }

        $this->programs = self::CERT_PROGRAMS;
        $this->load();
    }

    public function registerCandidate(string $name, string $email, string $program, string $level): string
    {
        if (!isset($this->programs[$program])) {
            throw new CertificationException("Unknown certification program: {$program}");
        }
        if (!in_array($level, $this->programs[$program]['levels'])) {
            throw new CertificationException("Invalid level '{$level}' for program '{$program}'");
        }

        $id = uniqid('cand_', true);
        $this->certifications[$id] = [
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'program' => $program,
            'level' => $level,
            'status' => 'registered',
            'score' => null,
            'registered_at' => date('c'),
            'certified_at' => null,
            'expires_at' => null,
        ];
        $this->save();
        return $id;
    }

    public function conductExam(string $certificationId, array $answers): array
    {
        $cert = $this->certifications[$certificationId] ?? null;
        if (!$cert) {
            throw new CertificationException("Certification not found");
        }

        $program = $this->programs[$cert['program']];
        $score = $this->gradeExam($answers, $program);
        $passed = $score >= $program['pass_threshold'];

        $examId = uniqid('exam_', true);
        $this->exams[$examId] = [
            'id' => $examId,
            'certification_id' => $certificationId,
            'score' => $score,
            'passed' => $passed,
            'conducted_at' => date('c'),
        ];

        $this->certifications[$certificationId]['status'] = $passed ? 'certified' : 'failed';
        $this->certifications[$certificationId]['score'] = $score;
        if ($passed) {
            $this->certifications[$certificationId]['certified_at'] = date('c');
            $this->certifications[$certificationId]['expires_at'] = date('c', strtotime("+{$program['validity_years']} years"));
        }

        $this->save();
        return ['exam_id' => $examId, 'score' => $score, 'passed' => $passed];
    }

    public function recertify(string $certificationId): array
    {
        $cert = $this->certifications[$certificationId] ?? null;
        if (!$cert) {
            throw new CertificationException("Certification not found");
        }

        $cert['status'] = 'recertifying';
        $this->save();

        return $this->conductExam($certificationId, []);
    }

    public function getCertification(string $id): ?array
    {
        return $this->certifications[$id] ?? null;
    }

    public function listCertifications(string $program = '', string $status = ''): array
    {
        $results = $this->certifications;
        if ($program) $results = array_filter($results, fn($c) => $c['program'] === $program);
        if ($status) $results = array_filter($results, fn($c) => $c['status'] === $status);
        return array_values($results);
    }

    public function getPrograms(): array
    {
        return $this->programs;
    }

    public function getStats(): array
    {
        $all = $this->certifications;
        return [
            'total_candidates' => count($all),
            'certified' => count(array_filter($all, fn($c) => $c['status'] === 'certified')),
            'by_program' => array_count_values(array_column($all, 'program')),
            'average_score' => count($all) > 0
                ? round(array_sum(array_column($all, 'score')) / count($all), 1)
                : 0,
        ];
    }

    private function gradeExam(array $answers, array $program): int
    {
        $totalQuestions = count($program['topics']) * 5;
        $correct = rand(0, $totalQuestions);
        return (int)round(($correct / max($totalQuestions, 1)) * 100);
    }

    private function load(): void
    {
        $file = $this->dataDir . '/certifications.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->certifications = $data['certifications'] ?? [];
                $this->exams = $data['exams'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/certifications.json',
            json_encode(['certifications' => $this->certifications, 'exams' => $this->exams], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
