<?php

namespace Cypher\RuntimeEngine\Memory;

class MemoryException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[MemoryManager] {$message}");
    }
}
