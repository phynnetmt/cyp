<?php

namespace Cypher\Cloud\ManagedServices;

class ManagedVectorDB
{
    private array $indexes = [];
    private array $vectorData = [];
    private string $dataDir;

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-cloud/vectors');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }
        $this->load();
    }

    public function createIndex(string $name, int $dimensions = 1536, string $metric = 'cosine'): array
    {
        if ($dimensions <= 0) {
            throw new \InvalidArgumentException("Dimensions must be positive, got {$dimensions}");
        }
        if (empty($name)) {
            throw new \InvalidArgumentException("Index name cannot be empty");
        }

        $id = uniqid('idx_', true);
        $idx = [
            'id' => $id,
            'name' => $name,
            'dimensions' => $dimensions,
            'metric' => $metric,
            'status' => 'active',
            'vector_count' => 0,
            'created_at' => date('c'),
        ];

        $this->indexes[$id] = $idx;
        $this->vectorData[$id] = [];
        $this->save();

        return $idx;
    }

    public function getIndex(string $id): ?array
    {
        return $this->indexes[$id] ?? null;
    }

    public function listIndexes(): array
    {
        return array_values($this->indexes);
    }

    public function deleteIndex(string $id): void
    {
        if (!isset($this->indexes[$id])) {
            throw new \RuntimeException("Vector index not found: {$id}");
        }
        unset($this->indexes[$id]);
        unset($this->vectorData[$id]);
        $this->save();
    }

    public function upsertVectors(string $indexId, array $vectors): void
    {
        if (!isset($this->indexes[$indexId])) {
            throw new \RuntimeException("Vector index not found: {$indexId}");
        }

        $dimensions = $this->indexes[$indexId]['dimensions'];
        foreach ($vectors as $vec) {
            if (count($vec['embedding'] ?? []) !== $dimensions) {
                throw new \InvalidArgumentException(
                    "Vector dimension mismatch: expected {$dimensions}, got " . count($vec['embedding'] ?? [])
                );
            }
            $id = $vec['id'] ?? uniqid('v_', true);
            $this->vectorData[$indexId][$id] = $vec;
        }

        $this->indexes[$indexId]['vector_count'] = count($this->vectorData[$indexId]);
        $this->save();
    }

    public function search(string $indexId, array $queryVector, int $topK = 10): array
    {
        if (!isset($this->indexes[$indexId])) {
            throw new \RuntimeException("Vector index not found: {$indexId}");
        }

        $vectors = $this->vectorData[$indexId] ?? [];
        if (empty($vectors)) {
            return ['results' => [], 'total' => 0];
        }

        $results = [];
        foreach ($vectors as $id => $vec) {
            $similarity = $this->cosineSimilarity($queryVector, $vec['embedding']);
            $results[] = [
                'id' => $id,
                'score' => $similarity,
                'metadata' => $vec['metadata'] ?? [],
            ];
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        return ['results' => array_slice($results, 0, $topK), 'total' => count($vectors)];
    }

    public function getStats(): array
    {
        return [
            'total_indexes' => count($this->indexes),
            'total_vectors' => array_sum(array_map(fn($i) => $i['vector_count'] ?? 0, $this->indexes)),
        ];
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
        $denom = sqrt($normA) * sqrt($normB);
        return $denom === 0.0 ? 0 : $dotProduct / $denom;
    }

    private function load(): void
    {
        $file = $this->dataDir . '/indexes.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if (is_array($data)) {
                $this->indexes = $data['indexes'] ?? [];
                $this->vectorData = $data['vectors'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/indexes.json',
            json_encode(['indexes' => $this->indexes, 'vectors' => $this->vectorData], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
