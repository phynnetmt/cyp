<?php

namespace Cypher\Ecosystem\Governance;

class GovernanceException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[EcosystemGovernance] {$message}");
    }
}
