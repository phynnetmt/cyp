<?php

namespace Cypher\RuntimeEngine\AiRuntime;

class AiRuntimeException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[AiRuntime] {$message}");
    }
}
