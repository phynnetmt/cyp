<?php

namespace Cypher\RuntimeEngine\Concurrency;

class ConcurrencyException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[Concurrency] {$message}");
    }
}
