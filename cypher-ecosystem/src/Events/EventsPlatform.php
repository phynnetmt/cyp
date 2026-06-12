<?php

namespace Cypher\Ecosystem\Events;

class EventsPlatform
{
    private array $conferences = [];
    private array $cfpSubmissions = [];
    private array $sponsors = [];
    private string $dataDir;

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-ecosystem/events');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }
        $this->load();
    }

    public function createConference(string $name, string $description, string $location, string $date, int $capacity, array $tracks = []): array
    {
        $id = uniqid('conf_', true);
        $conference = [
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'location' => $location,
            'date' => $date,
            'capacity' => $capacity,
            'tracks' => $tracks,
            'registered_attendees' => 0,
            'submissions' => 0,
            'status' => 'planned',
            'created_at' => date('c'),
        ];
        $this->conferences[$id] = $conference;
        $this->save();
        return $conference;
    }

    public function registerAttendee(string $conferenceId, string $userId, string $ticketType = 'standard'): string
    {
        if (!isset($this->conferences[$conferenceId])) {
            throw new EventsException("Conference not found");
        }

        $id = uniqid('reg_', true);
        $this->conferences[$conferenceId]['registered_attendees']++;

        $registration = [
            'id' => $id,
            'conference_id' => $conferenceId,
            'user_id' => $userId,
            'ticket_type' => $ticketType,
            'registered_at' => date('c'),
        ];

        if (is_dir($this->dataDir)) {
            file_put_contents(
                $this->dataDir . '/registrations.json',
                json_encode($registration, JSON_PRETTY_PRINT) . "\n",
                FILE_APPEND | LOCK_EX
            );
        }

        $this->save();
        return $id;
    }

    public function submitCFP(string $conferenceId, string $title, string $abstract, string $speaker, string $track = ''): string
    {
        if (!isset($this->conferences[$conferenceId])) {
            throw new EventsException("Conference not found");
        }

        $id = uniqid('cfp_', true);
        $this->cfpSubmissions[$id] = [
            'id' => $id,
            'conference_id' => $conferenceId,
            'title' => $title,
            'abstract' => $abstract,
            'speaker' => $speaker,
            'track' => $track,
            'status' => 'submitted',
            'score' => 0,
            'submitted_at' => date('c'),
        ];

        $this->conferences[$conferenceId]['submissions']++;
        $this->save();

        return $id;
    }

    public function reviewCFP(string $submissionId, int $score, string $feedback): void
    {
        if (isset($this->cfpSubmissions[$submissionId])) {
            $this->cfpSubmissions[$submissionId]['score'] = $score;
            $this->cfpSubmissions[$submissionId]['feedback'] = $feedback;
            $this->cfpSubmissions[$submissionId]['status'] = $score >= 7 ? 'accepted' : 'rejected';
            $this->save();
        }
    }

    public function addSponsor(string $conferenceId, string $name, string $tier, float $amount): array
    {
        $id = uniqid('sponsor_', true);
        $sponsor = [
            'id' => $id,
            'conference_id' => $conferenceId,
            'name' => $name,
            'tier' => $tier,
            'amount' => $amount,
            'status' => 'confirmed',
            'created_at' => date('c'),
        ];
        $this->sponsors[$id] = $sponsor;
        $this->save();
        return $sponsor;
    }

    public function listConferences(string $status = ''): array
    {
        if ($status) {
            return array_values(array_filter($this->conferences, fn($c) => $c['status'] === $status));
        }
        return array_values($this->conferences);
    }

    public function getSchedule(string $conferenceId): array
    {
        $conf = $this->conferences[$conferenceId] ?? null;
        if (!$conf) return [];

        $accepted = array_filter($this->cfpSubmissions, fn($s) =>
            $s['conference_id'] === $conferenceId && $s['status'] === 'accepted'
        );

        return [
            'conference' => $conf['name'],
            'tracks' => $conf['tracks'],
            'sessions' => array_values($accepted),
        ];
    }

    public function getStats(): array
    {
        return [
            'conferences' => count($this->conferences),
            'total_attendees' => array_sum(array_column($this->conferences, 'registered_attendees')),
            'total_submissions' => count($this->cfpSubmissions),
            'total_sponsors' => count($this->sponsors),
            'sponsorship_revenue' => array_sum(array_column($this->sponsors, 'amount')),
        ];
    }

    private function load(): void
    {
        $file = $this->dataDir . '/events.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->conferences = $data['conferences'] ?? [];
                $this->cfpSubmissions = $data['submissions'] ?? [];
                $this->sponsors = $data['sponsors'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/events.json',
            json_encode([
                'conferences' => $this->conferences,
                'submissions' => $this->cfpSubmissions,
                'sponsors' => $this->sponsors,
            ], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
