<?php

namespace Cypher\Enterprise\EnterpriseAgents;

class EnterpriseAgentException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[EnterpriseAgents] {$message}");
    }
}
