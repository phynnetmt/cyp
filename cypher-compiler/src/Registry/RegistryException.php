<?php

namespace Cypher\Compiler\Registry;

class RegistryException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[Registry] {$message}");
    }
}
