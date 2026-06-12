<?php

namespace Cypher\Runtime\Memory;

class ShortTermMemory implements MemoryStoreInterface
{
    private array $items = [];
    private int $maxItems;
    private int $ttl;

    public function __construct(array $config = [])
    {
        $this->maxItems = $config['max_items'] ?? 100;
        $this->ttl = $config['ttl'] ?? 3600;
    }

    public function store(array $data): void
    {
        $this->prune();

        $id = $data['id'] ?? uniqid('mem_', true);
        $this->items[$id] = [
            'id' => $id,
            'content' => $data['content'] ?? $data['input'] ?? '',
            'type' => $data['type'] ?? 'generic',
            'metadata' => $data['metadata'] ?? [],
            'timestamp' => $data['timestamp'] ?? time(),
            'expires_at' => time() + $this->ttl,
        ];

        if (count($this->items) > $this->maxItems) {
            array_shift($this->items);
        }
    }

    public function search(string $query, int $limit = 5): array
    {
        $this->prune();
        $results = [];

        foreach ($this->items as $item) {
            $score = $this->similarity($query, $item['content']);
            if ($score > 0) {
                $results[] = array_merge($item, ['score' => $score]);
            }
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($results, 0, $limit);
    }

    public function recall(string $key): ?array
    {
        $this->prune();
        return $this->items[$key] ?? null;
    }

    public function forget(string $key): void
    {
        unset($this->items[$key]);
    }

    public function clear(): void
    {
        $this->items = [];
    }

    public function stats(): array
    {
        return [
            'type' => 'short_term',
            'items' => count($this->items),
            'max_items' => $this->maxItems,
            'ttl' => $this->ttl,
        ];
    }

    private function prune(): void
    {
        $now = time();
        foreach ($this->items as $id => $item) {
            if ($item['expires_at'] <= $now) {
                unset($this->items[$id]);
            }
        }
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
}
