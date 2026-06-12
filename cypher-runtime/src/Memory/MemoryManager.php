<?php

namespace Cypher\Runtime\Memory;

class MemoryManager
{
    private array $stores = [];
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;

        if ($config['short_term'] ?? true) {
            $this->stores['short_term'] = new ShortTermMemory($config['short_term_config'] ?? []);
        }
        if ($config['long_term'] ?? true) {
            $this->stores['long_term'] = new LongTermMemory($config['long_term_config'] ?? []);
        }
        if ($config['semantic'] ?? false) {
            $this->stores['semantic'] = new SemanticMemory($config['semantic_config'] ?? []);
        }
        if ($config['episodic'] ?? false) {
            $this->stores['episodic'] = new EpisodicMemory($config['episodic_config'] ?? []);
        }
    }

    public function store(array $data, string $store = 'short_term'): void
    {
        $store = $this->getStore($store);
        $store->store($data);
    }

    public function search(string $query, int $limit = 5, string $store = 'short_term'): array
    {
        $store = $this->getStore($store);
        return $store->search($query, $limit);
    }

    public function recall(string $key, string $store = 'long_term'): ?array
    {
        $store = $this->getStore($store);
        return $store->recall($key);
    }

    public function forget(string $key, string $store = 'short_term'): void
    {
        $store = $this->getStore($store);
        $store->forget($key);
    }

    public function clear(string $store = 'short_term'): void
    {
        $store = $this->getStore($store);
        $store->clear();
    }

    public function getStats(): array
    {
        $stats = [];
        foreach ($this->stores as $name => $store) {
            $stats[$name] = $store->stats();
        }
        return $stats;
    }

    private function getStore(string $name): MemoryStoreInterface
    {
        if (!isset($this->stores[$name])) {
            throw new MemoryException("Memory store '{$name}' not available. Enable it in config.");
        }
        return $this->stores[$name];
    }
}
