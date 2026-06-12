<?php

namespace Cypher\Ecosystem\University;

class UniversityProgram
{
    private array $partnerships = [];
    private array $curricula = [];
    private array $researchGrants = [];
    private array $studentAmbassadors = [];
    private string $dataDir;

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-ecosystem/university');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }
        $this->load();
    }

    public function createPartnership(string $universityName, string $country, string $contactEmail, string $tier = 'associate'): array
    {
        $id = uniqid('uni_', true);
        $partnership = [
            'id' => $id,
            'university_name' => $universityName,
            'country' => $country,
            'contact_email' => $contactEmail,
            'tier' => $tier,
            'status' => 'active',
            'students_reached' => 0,
            'courses_adopted' => 0,
            'joined_at' => date('c'),
        ];
        $this->partnerships[$id] = $partnership;
        $this->save();
        return $partnership;
    }

    public function publishCurriculum(string $title, string $description, string $level, int $creditHours, array $modules): array
    {
        $id = uniqid('curr_', true);
        $curriculum = [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'level' => $level,
            'credit_hours' => $creditHours,
            'modules' => $modules,
            'adoptions' => 0,
            'status' => 'published',
            'created_at' => date('c'),
        ];
        $this->curricula[$id] = $curriculum;
        $this->save();
        return $curriculum;
    }

    public function adoptCurriculum(string $curriculumId, string $partnershipId): void
    {
        if (isset($this->curricula[$curriculumId]) && isset($this->partnerships[$partnershipId])) {
            $this->curricula[$curriculumId]['adoptions']++;
            $this->partnerships[$partnershipId]['courses_adopted']++;
            $this->save();
        }
    }

    public function createResearchGrant(string $title, string $researcher, string $institution, float $amount, string $topic): array
    {
        $id = uniqid('grant_', true);
        $grant = [
            'id' => $id,
            'title' => $title,
            'researcher' => $researcher,
            'institution' => $institution,
            'amount' => $amount,
            'topic' => $topic,
            'status' => 'active',
            'deliverables' => [],
            'awarded_at' => date('c'),
            'completed_at' => null,
        ];
        $this->researchGrants[$id] = $grant;
        $this->save();
        return $grant;
    }

    public function submitDeliverable(string $grantId, string $title, string $content): void
    {
        if (isset($this->researchGrants[$grantId])) {
            $this->researchGrants[$grantId]['deliverables'][] = [
                'title' => $title,
                'content' => $content,
                'submitted_at' => date('c'),
            ];
            $this->save();
        }
    }

    public function nominateStudentAmbassador(string $studentName, string $email, string $university): string
    {
        $id = uniqid('stuamb_', true);
        $this->studentAmbassadors[$id] = [
            'id' => $id,
            'student_name' => $studentName,
            'email' => $email,
            'university' => $university,
            'status' => 'active',
            'events_organized' => 0,
            'nominated_at' => date('c'),
        ];
        $this->save();
        return $id;
    }

    public function recordStudentEvent(string $ambassadorId): void
    {
        if (isset($this->studentAmbassadors[$ambassadorId])) {
            $this->studentAmbassadors[$ambassadorId]['events_organized']++;
            $this->save();
        }
    }

    public function listPartnerships(string $country = ''): array
    {
        if ($country) {
            return array_values(array_filter($this->partnerships, fn($p) => $p['country'] === $country));
        }
        return array_values($this->partnerships);
    }

    public function getStats(): array
    {
        return [
            'partnerships' => count($this->partnerships),
            'curricula' => count($this->curricula),
            'research_grants' => count($this->researchGrants),
            'total_funding' => array_sum(array_column($this->researchGrants, 'amount')),
            'student_ambassadors' => count($this->studentAmbassadors),
        ];
    }

    private function load(): void
    {
        $file = $this->dataDir . '/university.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->partnerships = $data['partnerships'] ?? [];
                $this->curricula = $data['curricula'] ?? [];
                $this->researchGrants = $data['grants'] ?? [];
                $this->studentAmbassadors = $data['ambassadors'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/university.json',
            json_encode([
                'partnerships' => $this->partnerships,
                'curricula' => $this->curricula,
                'grants' => $this->researchGrants,
                'ambassadors' => $this->studentAmbassadors,
            ], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
