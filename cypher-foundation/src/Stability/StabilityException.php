<?php

namespace Cypher\Foundation\Stability;

class StabilityException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[Stability] {$message}");
    }
}
