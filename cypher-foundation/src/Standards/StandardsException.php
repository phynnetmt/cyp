<?php

namespace Cypher\Foundation\Standards;

class StandardsException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[Standards] {$message}");
    }
}
