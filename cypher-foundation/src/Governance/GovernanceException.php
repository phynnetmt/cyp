<?php

namespace Cypher\Foundation\Governance;

class GovernanceException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[Governance] {$message}");
    }
}
