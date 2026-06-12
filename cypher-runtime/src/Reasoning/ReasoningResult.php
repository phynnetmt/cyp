<?php

namespace Cypher\Runtime\Reasoning;

class ReasoningResult
{
    public function __construct(
        public readonly string $output,
        public readonly string $reasoning = '',
        public readonly array $toolCalls = [],
        public readonly float $confidence = 0.0,
        public readonly array $steps = [],
    ) {}
}
