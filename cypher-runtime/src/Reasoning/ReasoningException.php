<?php

namespace Cypher\Runtime\Reasoning;

class ReasoningException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[Reasoning] {$message}");
    }
}
