<?php

namespace Cypher\RuntimeEngine\Sandbox;

class SandboxException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[Sandbox] {$message}");
    }
}
