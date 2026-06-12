<?php

namespace Cypher\Runtime\Memory;

class SemanticMemory implements MemoryStoreInterface
{
    private array $graph = [];
    private string $storagePath;

    public function __construct(array $config = [])
    {
        $this->storagePath = $config['storage_path'] ?? (sys_get_temp_dir() . '/cyp_semantic');
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0777, true);
        }
        $this->load();
    }

    public function store(array $data): void
    {
        $id = $data['id'] ?? uniqid('sem_', true);
        $content = $data['content'] ?? '';
        $concepts = $this->extractConcepts($content);

        $this->graph[$id] = [
            'id' => $id,
            'content' => $content,
            'concepts' => $concepts,
            'relations' => $data['relations'] ?? [],
            'timestamp' => $data['timestamp'] ?? time(),
        ];

        // Link related concepts
        foreach ($this->graph as $existingId => $existing) {
            $shared = array_intersect($concepts, $existing['concepts']);
            if (!empty($shared)) {
                $this->graph[$id]['relations'][] = $existingId;
                $this->graph[$existingId]['relations'][] = $id;
            }
        }

        $this->save();
    }

    public function search(string $query, int $limit = 5): array
    {
        $queryConcepts = $this->extractConcepts($query);
        $results = [];

        foreach ($this->graph as $item) {
            $shared = array_intersect($queryConcepts, $item['concepts']);
            if (!empty($shared)) {
                $score = count($shared) / max(count($queryConcepts), 1);
                $results[] = array_merge($item, ['score' => $score]);
            }
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($results, 0, $limit);
    }

    public function recall(string $key): ?array
    {
        return $this->graph[$key] ?? null;
    }

    public function forget(string $key): void
    {
        if (isset($this->graph[$key])) {
            foreach ($this->graph[$key]['relations'] as $related) {
                if (isset($this->graph[$related])) {
                    $this->graph[$related]['relations'] = array_diff(
                        $this->graph[$related]['relations'], [$key]
                    );
                }
            }
            unset($this->graph[$key]);
            $this->save();
        }
    }

    public function clear(): void
    {
        $this->graph = [];
        $this->save();
    }

    public function stats(): array
    {
        return [
            'type' => 'semantic',
            'nodes' => count($this->graph),
            'edges' => $this->countEdges(),
        ];
    }

    public function getGraph(): array
    {
        return $this->graph;
    }

    private function extractConcepts(string $text): array
    {
        $text = strtolower($text);
        $words = str_word_count($text, 1);
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
                       'of', 'with', 'by', 'from', 'is', 'are', 'was', 'were', 'be', 'been',
                       'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would',
                       'could', 'should', 'may', 'might', 'shall', 'can', 'need', 'dare',
                       'ought', 'used', 'this', 'that', 'these', 'those', 'i', 'you', 'he',
                       'she', 'it', 'we', 'they', 'me', 'him', 'her', 'us', 'them'];

        return array_values(array_unique(array_diff($words, $stopWords)));
    }

    private function countEdges(): int
    {
        $count = 0;
        foreach ($this->graph as $node) {
            $count += count($node['relations']);
        }
        return (int)($count / 2);
    }

    private function load(): void
    {
        $file = $this->storagePath . '/semantic.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) $this->graph = $data;
        }
    }

    private function save(): void
    {
        $file = $this->storagePath . '/semantic.json';
        $tmp = $file . '.' . uniqid('', true) . '.tmp';
        $written = file_put_contents($tmp, json_encode($this->graph, JSON_PRETTY_PRINT), LOCK_EX);
        if ($written !== false) {
            rename($tmp, $file);
        }
    }
}
