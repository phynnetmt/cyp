<?php

namespace Cypher\Ecosystem\Startups;

class StartupException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[StartupProgram] {$message}");
    }
}
