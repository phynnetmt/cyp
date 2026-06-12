<?php

namespace Cypher\RuntimeEngine\VM;

class VMException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[VM] {$message}");
    }
}
