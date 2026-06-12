<?php

namespace Cypher\Compiler\PackageManager;

class PackageManagerException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[PackageManager] {$message}");
    }
}
