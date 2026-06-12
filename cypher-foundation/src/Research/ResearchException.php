<?php

namespace Cypher\Foundation\Research;

class ResearchException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[Research] {$message}");
    }
}
