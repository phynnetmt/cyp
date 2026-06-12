<?php

namespace Cypher\RuntimeEngine\VM;

class VMResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $output = '',
        public readonly ?string $error = null,
        public readonly int $steps = 0,
        public readonly float $durationMs = 0,
        public readonly array $stack = [],
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'output' => $this->output,
            'steps' => $this->steps,
            'duration_ms' => round($this->durationMs, 2),
            'error' => $this->error,
        ];
    }
}
