<?php

namespace Cypher\Foundation\Chapters;

class GlobalChapters
{
    private array $chapters = [];
    private array $leads = [];
    private array $events = [];
    private string $dataDir;

    private const REGIONS = [
        'north_america' => 'North America',
        'europe' => 'Europe',
        'africa' => 'Africa',
        'asia' => 'Asia',
        'south_america' => 'South America',
        'middle_east' => 'Middle East',
        'oceania' => 'Oceania',
    ];

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-foundation/chapters');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }

        foreach (self::REGIONS as $key => $name) {
            $this->chapters[$key] = [
                'region' => $name,
                'status' => 'inactive',
                'members' => 0,
                'events_held' => 0,
                'lead' => null,
            ];
        }

        $this->load();
    }

    public function activateChapter(string $regionKey): array
    {
        if (!isset($this->chapters[$regionKey])) {
            throw new ChaptersException("Unknown region: {$regionKey}");
        }
        $this->chapters[$regionKey]['status'] = 'active';
        $this->chapters[$regionKey]['activated_at'] = date('c');
        $this->save();
        return $this->chapters[$regionKey];
    }

    public function appointLead(string $regionKey, string $name, string $email): array
    {
        if (!isset($this->chapters[$regionKey])) {
            throw new ChaptersException("Unknown region: {$regionKey}");
        }

        $id = uniqid('lead_', true);
        $lead = [
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'region' => $regionKey,
            'appointed_at' => date('c'),
        ];

        $this->leads[$id] = $lead;
        $this->chapters[$regionKey]['lead'] = $name;
        $this->save();

        return $lead;
    }

    public function recordEvent(string $regionKey, string $name, string $date, int $attendees): array
    {
        if (!isset($this->chapters[$regionKey])) {
            throw new ChaptersException("Unknown region: {$regionKey}");
        }

        $id = uniqid('evt_', true);
        $event = [
            'id' => $id,
            'region' => $regionKey,
            'name' => $name,
            'date' => $date,
            'attendees' => $attendees,
        ];

        $this->events[$id] = $event;
        $this->chapters[$regionKey]['events_held']++;
        $this->chapters[$regionKey]['members'] = max($this->chapters[$regionKey]['members'], $attendees);
        $this->save();

        return $event;
    }

    public function getChapters(string $status = ''): array
    {
        if ($status) {
            return array_values(array_filter($this->chapters, fn($c) => $c['status'] === $status));
        }
        return $this->chapters;
    }

    public function getRegions(): array
    {
        return self::REGIONS;
    }

    public function getLeads(string $regionKey = ''): array
    {
        if ($regionKey) {
            return array_values(array_filter($this->leads, fn($l) => $l['region'] === $regionKey));
        }
        return array_values($this->leads);
    }

    public function getStats(): array
    {
        $active = array_filter($this->chapters, fn($c) => $c['status'] === 'active');
        return [
            'total_regions' => count(self::REGIONS),
            'active_chapters' => count($active),
            'total_members' => array_sum(array_column($this->chapters, 'members')),
            'total_events' => array_sum(array_column($this->chapters, 'events_held')),
        ];
    }

    private function load(): void
    {
        $file = $this->dataDir . '/chapters.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                if (isset($data['chapters'])) {
                    foreach ($data['chapters'] as $key => $c) {
                        if (isset($this->chapters[$key])) {
                            $this->chapters[$key] = array_merge($this->chapters[$key], $c);
                        }
                    }
                }
                $this->leads = $data['leads'] ?? [];
                $this->events = $data['events'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/chapters.json',
            json_encode([
                'chapters' => $this->chapters,
                'leads' => $this->leads,
                'events' => $this->events,
            ], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
