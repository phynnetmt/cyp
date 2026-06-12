<?php

namespace Cypher\Ecosystem\Advocacy;

class AdvocacyProgram
{
    private array $advocates = [];
    private array $speakerBureau = [];
    private array $evangelists = [];
    private string $dataDir;

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-ecosystem/advocacy');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }
        $this->load();
    }

    public function onboardAdvocate(string $name, string $email, string $region, string $specialty, string $level = 'community'): array
    {
        $id = uniqid('adv_', true);
        $advocate = [
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'region' => $region,
            'specialty' => $specialty,
            'level' => $level,
            'status' => 'active',
            'talks_given' => 0,
            'articles_written' => 0,
            'workshops_conducted' => 0,
            'joined_at' => date('c'),
        ];
        $this->advocates[$id] = $advocate;
        $this->save();
        return $advocate;
    }

    public function recordActivity(string $advocateId, string $type): void
    {
        if (isset($this->advocates[$advocateId])) {
            match ($type) {
                'talk' => $this->advocates[$advocateId]['talks_given']++,
                'article' => $this->advocates[$advocateId]['articles_written']++,
                'workshop' => $this->advocates[$advocateId]['workshops_conducted']++,
                default => null,
            };
            $this->save();
        }
    }

    public function registerSpeaker(string $name, string $email, string $topic, string $bio): string
    {
        $id = uniqid('spkr_', true);
        $this->speakerBureau[$id] = [
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'topic' => $topic,
            'bio' => $bio,
            'status' => 'available',
            'talks_scheduled' => 0,
            'registered_at' => date('c'),
        ];
        $this->save();
        return $id;
    }

    public function scheduleTalk(string $speakerId): void
    {
        if (isset($this->speakerBureau[$speakerId])) {
            $this->speakerBureau[$speakerId]['talks_scheduled']++;
            $this->save();
        }
    }

    public function addEvangelist(string $name, string $email, string $focusArea): array
    {
        $id = uniqid('evan_', true);
        $evangelist = [
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'focus_area' => $focusArea,
            'status' => 'active',
            'conferences_keynoted' => 0,
            'joined_at' => date('c'),
        ];
        $this->evangelists[$id] = $evangelist;
        $this->save();
        return $evangelist;
    }

    public function recordKeynote(string $evangelistId): void
    {
        if (isset($this->evangelists[$evangelistId])) {
            $this->evangelists[$evangelistId]['conferences_keynoted']++;
            $this->save();
        }
    }

    public function listAdvocates(string $region = ''): array
    {
        if ($region) {
            return array_values(array_filter($this->advocates, fn($a) => $a['region'] === $region));
        }
        return array_values($this->advocates);
    }

    public function listSpeakers(string $topic = ''): array
    {
        if ($topic) {
            return array_values(array_filter($this->speakerBureau, fn($s) => $s['topic'] === $topic));
        }
        return array_values($this->speakerBureau);
    }

    public function getStats(): array
    {
        return [
            'advocates' => count($this->advocates),
            'speakers' => count($this->speakerBureau),
            'evangelists' => count($this->evangelists),
            'total_talks' => array_sum(array_column($this->advocates, 'talks_given')),
            'total_articles' => array_sum(array_column($this->advocates, 'articles_written')),
        ];
    }

    private function load(): void
    {
        $file = $this->dataDir . '/advocacy.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->advocates = $data['advocates'] ?? [];
                $this->speakerBureau = $data['speakers'] ?? [];
                $this->evangelists = $data['evangelists'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/advocacy.json',
            json_encode([
                'advocates' => $this->advocates,
                'speakers' => $this->speakerBureau,
                'evangelists' => $this->evangelists,
            ], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
