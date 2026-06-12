<?php

namespace Cypher\Runtime\Agent;

class AgentException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[AgentRuntime] {$message}");
    }
}
