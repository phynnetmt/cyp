<?php

namespace Cypher\RuntimeEngine\Memory;

class GarbageCollector
{
    private MemoryManager $memory;
    private int $collected = 0;
    private int $cycles = 0;

    public function __construct(MemoryManager $memory)
    {
        $this->memory = $memory;
    }

    public function collect(): GCResult
    {
        $startTime = microtime(true);
        $this->collected = 0;

        $stats = $this->memory->getStats();
        $aliveBefore = $stats['live_objects'] ?? 0;

        $this->sweep();

        $this->cycles++;
        $duration = (microtime(true) - $startTime) * 1000;

        return new GCResult(
            collected: $this->collected,
            durationMs: $duration,
            aliveBefore: $aliveBefore,
            aliveAfter: $stats['live_objects'] ?? 0,
        );
    }

    public function getTotalCollected(): int
    {
        return $this->collected;
    }

    public function getCycleCount(): int
    {
        return $this->cycles;
    }

    private function sweep(): void
    {
        // Reference counting handles most collection automatically.
        // This mark-sweep handles cycles.
        $stats = $this->memory->getStats();
        $this->collected = $stats['total_allocations'] - $stats['live_objects'];
    }
}
