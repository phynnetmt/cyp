<?php

namespace Cypher\Runtime\Agent;

class AgentResponse
{
    public function __construct(
        public readonly string $output,
        public readonly string $reasoning = '',
        public readonly array $toolResults = [],
        public readonly float $confidence = 0.0,
    ) {}

    public function toArray(): array
    {
        return [
            'output' => $this->output,
            'reasoning' => $this->reasoning,
            'tool_results' => $this->toolResults,
            'confidence' => $this->confidence,
        ];
    }
}
