<?php

namespace Cypher\Compiler\Interpreter;

class InterpreterResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $output = '',
        public readonly ?string $error = null,
        public readonly int $steps = 0,
        public readonly float $durationMs = 0,
    ) {}

    public function hasErrors(): bool
    {
        return $this->error !== null;
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'output' => $this->output,
            'error' => $this->error,
            'steps' => $this->steps,
            'duration_ms' => round($this->durationMs, 2),
        ];
    }
}
