<?php

namespace Cypher\Runtime\Memory;

class LongTermMemory implements MemoryStoreInterface
{
    private array $items = [];
    private string $storagePath;

    public function __construct(array $config = [])
    {
        $this->storagePath = $config['storage_path'] ?? (sys_get_temp_dir() . '/cyp_memory');
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0700, true);
        }
        $this->load();
    }

    public function store(array $data): void
    {
        $id = $data['id'] ?? uniqid('ltm_', true);
        $this->items[$id] = [
            'id' => $id,
            'content' => $data['content'] ?? $data['input'] ?? '',
            'type' => $data['type'] ?? 'generic',
            'metadata' => $data['metadata'] ?? [],
            'timestamp' => $data['timestamp'] ?? time(),
            'importance' => $data['importance'] ?? 0.5,
        ];
        $this->save();
    }

    public function search(string $query, int $limit = 5): array
    {
        $results = [];
        foreach ($this->items as $item) {
            $score = $this->similarity($query, $item['content']) * $item['importance'];
            if ($score > 0) {
                $results[] = array_merge($item, ['score' => $score]);
            }
        }
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($results, 0, $limit);
    }

    public function recall(string $key): ?array
    {
        return $this->items[$key] ?? null;
    }

    public function forget(string $key): void
    {
        unset($this->items[$key]);
        $this->save();
    }

    public function clear(): void
    {
        $this->items = [];
        $this->save();
    }

    public function stats(): array
    {
        return [
            'type' => 'long_term',
            'items' => count($this->items),
            'storage' => $this->storagePath,
        ];
    }

    private function similarity(string $query, string $content): float
    {
        $query = strtolower($query);
        $content = strtolower($content);
        $queryWords = array_unique(str_word_count($query, 1));
        $contentWords = str_word_count($content, 1);
        if (empty($queryWords) || empty($contentWords)) return 0;
        $matches = array_intersect($queryWords, $contentWords);
        return count($matches) / count($queryWords);
    }

    private function load(): void
    {
        $file = $this->storagePath . '/long_term.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if (is_array($data)) $this->items = $data;
        }
    }

    private function save(): void
    {
        $file = $this->storagePath . '/long_term.json';
        $tmp = $file . '.' . uniqid('', true) . '.tmp';
        $written = file_put_contents($tmp, json_encode($this->items, JSON_PRETTY_PRINT), LOCK_EX);
        if ($written !== false) {
            rename($tmp, $file);
        }
    }
}
