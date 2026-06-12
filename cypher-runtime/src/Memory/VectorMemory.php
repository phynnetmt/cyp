<?php

namespace Cypher\Runtime\Memory;

class VectorMemory implements MemoryStoreInterface
{
    private array $vectors = [];
    private int $dimensions;
    private string $storagePath;

    public function __construct(array $config = [])
    {
        $this->dimensions = $config['dimensions'] ?? 1536;
        $this->storagePath = $config['storage_path'] ?? (sys_get_temp_dir() . '/cyp_vectors');
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0700, true);
        }
        $this->load();
    }

    public function store(array $data): void
    {
        $id = $data['id'] ?? uniqid('vec_', true);
        $embedding = $data['embedding'] ?? $this->generateEmbedding($data['content'] ?? '');

        $this->vectors[$id] = [
            'id' => $id,
            'content' => $data['content'] ?? '',
            'embedding' => $embedding,
            'metadata' => $data['metadata'] ?? [],
            'timestamp' => $data['timestamp'] ?? time(),
        ];
        $this->save();
    }

    public function search(string $query, int $limit = 5): array
    {
        $queryEmbedding = $this->generateEmbedding($query);
        $results = [];

        foreach ($this->vectors as $id => $vec) {
            $similarity = $this->cosineSimilarity($queryEmbedding, $vec['embedding']);
            $results[] = array_merge($vec, ['score' => $similarity]);
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($results, 0, $limit);
    }

    public function recall(string $key): ?array
    {
        return $this->vectors[$key] ?? null;
    }

    public function forget(string $key): void
    {
        unset($this->vectors[$key]);
        $this->save();
    }

    public function clear(): void
    {
        $this->vectors = [];
        $this->save();
    }

    public function stats(): array
    {
        return [
            'type' => 'vector',
            'vectors' => count($this->vectors),
            'dimensions' => $this->dimensions,
        ];
    }

    public function getVector(string $id): ?array
    {
        return $this->vectors[$id]['embedding'] ?? null;
    }

    public function forgetByMetadata(string $metadataKey, string $metadataValue): void
    {
        foreach ($this->vectors as $id => $vec) {
            if (($vec['metadata'][$metadataKey] ?? null) === $metadataValue) {
                unset($this->vectors[$id]);
            }
        }
        $this->save();
    }

    private function generateEmbedding(string $text): array
    {
        $hash = md5($text);
        $seed = hexdec(substr($hash, 0, 8));
        mt_srand($seed);
        $vector = [];
        for ($i = 0; $i < $this->dimensions; $i++) {
            $vector[] = (mt_rand() / mt_getrandmax()) * 2 - 1;
        }
        $norm = sqrt(array_sum(array_map(fn($v) => $v * $v, $vector)));
        if ($norm > 0) {
            $vector = array_map(fn($v) => $v / $norm, $vector);
        }
        return $vector;
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0;
        $normA = 0;
        $normB = 0;
        $len = min(count($a), count($b));
        for ($i = 0; $i < $len; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }
        $normA = sqrt($normA);
        $normB = sqrt($normB);
        if ($normA === 0.0 || $normB === 0.0) return 0;
        return $dotProduct / ($normA * $normB);
    }

    private function load(): void
    {
        $file = $this->storagePath . '/vectors.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if (is_array($data)) $this->vectors = $data;
        }
    }

    private function save(): void
    {
        $file = $this->storagePath . '/vectors.json';
        $tmp = $file . '.' . uniqid('', true) . '.tmp';
        $written = file_put_contents($tmp, json_encode($this->vectors, JSON_PRETTY_PRINT), LOCK_EX);
        if ($written !== false) {
            rename($tmp, $file);
        }
    }
}
