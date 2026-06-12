<?php

namespace Cypher\Runtime\Memory;

interface MemoryStoreInterface
{
    public function store(array $data): void;
    public function search(string $query, int $limit = 5): array;
    public function recall(string $key): ?array;
    public function forget(string $key): void;
    public function clear(): void;
    public function stats(): array;
}
