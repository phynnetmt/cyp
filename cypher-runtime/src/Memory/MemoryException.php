<?php

namespace Cypher\Runtime\Memory;

class MemoryException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[Memory] {$message}");
    }
}
