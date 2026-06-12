<?php

namespace Cypher\Foundation\Research;

class ResearchProgram
{
    private array $grants = [];
    private array $scholarships = [];
    private array $academicConferences = [];
    private string $dataDir;

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-foundation/research');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }
        $this->load();
    }

    public function awardGrant(string $title, string $researcher, string $institution, float $amount, string $topic): array
    {
        $id = uniqid('grant_', true);
        $grant = [
            'id' => $id,
            'title' => $title,
            'researcher' => $researcher,
            'institution' => $institution,
            'amount' => $amount,
            'topic' => $topic,
            'status' => 'awarded',
            'awarded_at' => date('c'),
            'completed_at' => null,
        ];
        $this->grants[$id] = $grant;
        $this->save();
        return $grant;
    }

    public function completeGrant(string $grantId, array $deliverables): void
    {
        if (isset($this->grants[$grantId])) {
            $this->grants[$grantId]['status'] = 'completed';
            $this->grants[$grantId]['completed_at'] = date('c');
            $this->grants[$grantId]['deliverables'] = $deliverables;
            $this->save();
        }
    }

    public function awardScholarship(string $studentName, string $email, string $university, float $amount, string $program): string
    {
        $id = uniqid('schol_', true);
        $this->scholarships[$id] = [
            'id' => $id,
            'student_name' => $studentName,
            'email' => $email,
            'university' => $university,
            'amount' => $amount,
            'program' => $program,
            'status' => 'awarded',
            'awarded_at' => date('c'),
        ];
        $this->save();
        return $id;
    }

    public function organizeConference(string $name, string $location, string $date, string $focus): array
    {
        $id = uniqid('conf_', true);
        $conference = [
            'id' => $id,
            'name' => $name,
            'location' => $location,
            'date' => $date,
            'focus' => $focus,
            'submissions' => 0,
            'attendees' => 0,
            'status' => 'planned',
            'created_at' => date('c'),
        ];
        $this->academicConferences[$id] = $conference;
        $this->save();
        return $conference;
    }

    public function recordConferenceSubmission(string $conferenceId): void
    {
        if (isset($this->academicConferences[$conferenceId])) {
            $this->academicConferences[$conferenceId]['submissions']++;
            $this->save();
        }
    }

    public function recordConferenceAttendance(string $conferenceId, int $attendees): void
    {
        if (isset($this->academicConferences[$conferenceId])) {
            $this->academicConferences[$conferenceId]['attendees'] += $attendees;
            $this->save();
        }
    }

    public function getGrants(string $status = ''): array
    {
        if ($status) {
            return array_values(array_filter($this->grants, fn($g) => $g['status'] === $status));
        }
        return array_values($this->grants);
    }

    public function getStats(): array
    {
        return [
            'total_grants' => count($this->grants),
            'total_funding' => array_sum(array_column($this->grants, 'amount')),
            'scholarships_awarded' => count($this->scholarships),
            'scholarship_funding' => array_sum(array_column($this->scholarships, 'amount')),
            'conferences_organized' => count($this->academicConferences),
        ];
    }

    private function load(): void
    {
        $file = $this->dataDir . '/research.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->grants = $data['grants'] ?? [];
                $this->scholarships = $data['scholarships'] ?? [];
                $this->academicConferences = $data['conferences'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/research.json',
            json_encode([
                'grants' => $this->grants,
                'scholarships' => $this->scholarships,
                'conferences' => $this->academicConferences,
            ], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
