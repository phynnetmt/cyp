<?php

namespace Cypher\Enterprise\Compliance;

class ComplianceAssessment
{
    public function __construct(
        public readonly string $framework,
        public readonly string $status,
        public readonly float $score,
        public readonly array $results = [],
    ) {}

    public function toArray(): array
    {
        return [
            'framework' => $this->framework,
            'status' => $this->status,
            'score' => $this->score,
            'results' => $this->results,
        ];
    }
}
