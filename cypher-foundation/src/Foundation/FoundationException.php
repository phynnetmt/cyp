<?php

namespace Cypher\Foundation\Foundation;

class FoundationException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[Foundation] {$message}");
    }
}
