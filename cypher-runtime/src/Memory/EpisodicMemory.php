<?php

namespace Cypher\Runtime\Memory;

class EpisodicMemory implements MemoryStoreInterface
{
    private array $episodes = [];
    private string $storagePath;

    public function __construct(array $config = [])
    {
        $this->storagePath = $config['storage_path'] ?? (sys_get_temp_dir() . '/cyp_episodic');
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0777, true);
        }
        $this->load();
    }

    public function store(array $data): void
    {
        $id = $data['id'] ?? uniqid('epi_', true);
        $this->episodes[$id] = [
            'id' => $id,
            'content' => $data['content'] ?? '',
            'context' => $data['context'] ?? [],
            'emotion' => $data['emotion'] ?? 'neutral',
            'importance' => $data['importance'] ?? 0.3,
            'timestamp' => $data['timestamp'] ?? time(),
            'sequence' => count($this->episodes),
        ];
        $this->save();
    }

    public function search(string $query, int $limit = 5): array
    {
        $results = [];
        foreach ($this->episodes as $episode) {
            $score = $this->episodicMatch($query, $episode);
            if ($score > 0) {
                $results[] = array_merge($episode, ['score' => $score]);
            }
        }
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($results, 0, $limit);
    }

    public function recall(string $key): ?array
    {
        return $this->episodes[$key] ?? null;
    }

    public function forget(string $key): void
    {
        unset($this->episodes[$key]);
        $this->save();
    }

    public function clear(): void
    {
        $this->episodes = [];
        $this->save();
    }

    public function stats(): array
    {
        return [
            'type' => 'episodic',
            'episodes' => count($this->episodes),
        ];
    }

    public function getTimeline(int $limit = 10): array
    {
        $sorted = $this->episodes;
        usort($sorted, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);
        return array_slice($sorted, 0, $limit);
    }

    private function episodicMatch(string $query, array $episode): float
    {
        $content = strtolower($episode['content']);
        $query = strtolower($query);
        $queryWords = array_unique(str_word_count($query, 1));
        $contentWords = str_word_count($content, 1);
        if (empty($queryWords) || empty($contentWords)) return 0;

        $matches = array_intersect($queryWords, $contentWords);
        $textScore = count($matches) / count($queryWords);

        // Boost recent episodes
        $recencyBoost = min(1, 3600 / max(1, time() - $episode['timestamp']));
        $importanceBoost = $episode['importance'];

        return $textScore * (0.5 + 0.3 * $recencyBoost + 0.2 * $importanceBoost);
    }

    private function load(): void
    {
        $file = $this->storagePath . '/episodic.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) $this->episodes = $data;
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->storagePath . '/episodic.json',
            json_encode($this->episodes, JSON_PRETTY_PRINT)
        );
    }
}
