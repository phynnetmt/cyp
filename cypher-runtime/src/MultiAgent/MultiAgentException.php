<?php

namespace Cypher\Runtime\MultiAgent;

class MultiAgentException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[MultiAgent] {$message}");
    }
}
