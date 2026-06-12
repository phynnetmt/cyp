<?php

namespace Cypher\Runtime\Knowledge;

use Cypher\Runtime\Memory\VectorMemory;

class KnowledgeEngine
{
    private VectorMemory $vectorStore;
    private array $documents = [];
    private array $config;
    private string $storagePath;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->vectorStore = new VectorMemory($config['vector'] ?? []);
        $this->storagePath = $config['storage_path'] ?? (sys_get_temp_dir() . '/cyp_knowledge');
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0777, true);
        }
        $this->load();
    }

    public function ingest(string $content, array $metadata = []): string
    {
        $id = uniqid('doc_', true);
        $chunks = $this->chunk($content);

        $this->documents[$id] = [
            'id' => $id,
            'content' => $content,
            'metadata' => $metadata,
            'chunks' => count($chunks),
            'timestamp' => time(),
        ];

        foreach ($chunks as $i => $chunk) {
            $this->vectorStore->store([
                'id' => "{$id}_chunk_{$i}",
                'content' => $chunk,
                'metadata' => array_merge($metadata, ['document_id' => $id, 'chunk' => $i]),
            ]);
        }

        $this->save();

        return $id;
    }

    public function ingestFile(string $path, array $metadata = []): string
    {
        if (!file_exists($path)) {
            throw new KnowledgeException("File not found: {$path}");
        }
        $content = file_get_contents($path);
        $metadata['filename'] = basename($path);
        $metadata['path'] = $path;
        return $this->ingest($content, $metadata);
    }

    public function search(string $query, int $limit = 5): array
    {
        $vectorResults = $this->vectorStore->search($query, $limit);
        $results = [];

        foreach ($vectorResults as $vr) {
            $docId = $vr['metadata']['document_id'] ?? null;
            $results[] = [
                'content' => $vr['content'],
                'score' => $vr['score'],
                'document_id' => $docId,
                'document' => $docId && isset($this->documents[$docId]) ? $this->documents[$docId] : null,
            ];
        }

        return $results;
    }

    public function getDocument(string $id): ?array
    {
        return $this->documents[$id] ?? null;
    }

    public function listDocuments(): array
    {
        return $this->documents;
    }

    public function removeDocument(string $id): void
    {
        unset($this->documents[$id]);
        $this->vectorStore->forgetByMetadata('document_id', $id);
        $this->save();
    }

    public function getStats(): array
    {
        return [
            'documents' => count($this->documents),
            'vectors' => $this->vectorStore->stats(),
        ];
    }

    public function getVectorStore(): VectorMemory
    {
        return $this->vectorStore;
    }

    private function chunk(string $content, int $size = 500): array
    {
        if (strlen($content) <= $size) {
            return [$content];
        }

        $chunks = [];
        $words = explode(' ', $content);
        $current = '';

        foreach ($words as $word) {
            if (strlen($current) + strlen($word) + 1 > $size) {
                $chunks[] = trim($current);
                $current = $word;
            } else {
                $current .= ($current ? ' ' : '') . $word;
            }
        }

        if (trim($current)) {
            $chunks[] = trim($current);
        }

        return $chunks;
    }

    private function load(): void
    {
        $file = $this->storagePath . '/documents.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) $this->documents = $data;
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->storagePath . '/documents.json',
            json_encode($this->documents, JSON_PRETTY_PRINT)
        );
    }
}
