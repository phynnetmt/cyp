<?php

namespace Cypher\RuntimeEngine\Memory;

class GCResult
{
    public function __construct(
        public readonly int $collected,
        public readonly float $durationMs,
        public readonly int $aliveBefore,
        public readonly int $aliveAfter,
    ) {}

    public function toArray(): array
    {
        return [
            'collected' => $this->collected,
            'duration_ms' => round($this->durationMs, 2),
            'alive_before' => $this->aliveBefore,
            'alive_after' => $this->aliveAfter,
        ];
    }
}
