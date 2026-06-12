<?php

namespace Cypher\RuntimeEngine\Memory;

class MemoryManager
{
    private array $objects = [];
    private array $memoryPools = [];
    private int $allocatedBytes = 0;
    private int $peakBytes = 0;
    private int $totalAllocations = 0;

    public function __construct(array $config = [])
    {
        $this->memoryPools = [
            'small' => ['size' => 64, 'count' => 0, 'max' => 10000],
            'medium' => ['size' => 1024, 'count' => 0, 'max' => 5000],
            'large' => ['size' => 65536, 'count' => 0, 'max' => 500],
        ];
    }

    public function allocate(int $size): int
    {
        $id = count($this->objects);
        $pool = $this->selectPool($size);

        $this->objects[$id] = [
            'id' => $id,
            'size' => $size,
            'pool' => $pool,
            'ref_count' => 0,
            'data' => null,
        ];

        $this->memoryPools[$pool]['count']++;
        $this->allocatedBytes += $size;
        $this->peakBytes = max($this->peakBytes, $this->allocatedBytes);
        $this->totalAllocations++;

        return $id;
    }

    public function write(int $id, mixed $data): void
    {
        if (!isset($this->objects[$id])) {
            throw new MemoryException("Invalid memory address: {$id}");
        }
        $this->objects[$id]['data'] = $data;
    }

    public function read(int $id): mixed
    {
        if (!isset($this->objects[$id])) {
            throw new MemoryException("Invalid memory address: {$id}");
        }
        return $this->objects[$id]['data'];
    }

    public function ref(int $id): void
    {
        if (isset($this->objects[$id])) {
            $this->objects[$id]['ref_count']++;
        }
    }

    public function deref(int $id): void
    {
        if (isset($this->objects[$id])) {
            $this->objects[$id]['ref_count']--;
            if ($this->objects[$id]['ref_count'] <= 0) {
                $this->deallocate($id);
            }
        }
    }

    public function deallocate(int $id): void
    {
        if (!isset($this->objects[$id])) return;

        $pool = $this->objects[$id]['pool'];
        $size = $this->objects[$id]['size'];

        $this->memoryPools[$pool]['count']--;
        $this->allocatedBytes -= $size;
        unset($this->objects[$id]);
    }

    public function getStats(): array
    {
        return [
            'allocated_bytes' => $this->allocatedBytes,
            'peak_bytes' => $this->peakBytes,
            'total_allocations' => $this->totalAllocations,
            'live_objects' => count($this->objects),
            'pools' => $this->memoryPools,
        ];
    }

    public function collectGarbage(): GarbageCollector
    {
        return new GarbageCollector($this);
    }

    private function selectPool(int $size): string
    {
        if ($size <= 64) return 'small';
        if ($size <= 1024) return 'medium';
        return 'large';
    }
}
