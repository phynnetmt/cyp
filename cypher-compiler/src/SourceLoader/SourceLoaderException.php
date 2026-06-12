<?php

namespace Cypher\Compiler\SourceLoader;

class SourceLoaderException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[SourceLoader] {$message}");
    }
}
