<?php

namespace Cypher\Foundation\Security;

class SecurityException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[FoundationSecurity] {$message}");
    }
}
