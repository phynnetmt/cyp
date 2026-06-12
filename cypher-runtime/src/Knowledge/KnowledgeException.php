<?php

namespace Cypher\Runtime\Knowledge;

class KnowledgeException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[Knowledge] {$message}");
    }
}
