<?php

namespace Cypher\Enterprise\Governance;

class PolicyEvaluation
{
    public function __construct(
        public readonly bool $allowed,
        public readonly array $reasons = [],
    ) {}

    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'reasons' => $this->reasons,
        ];
    }
}
