<?php

namespace Cypher\Enterprise\Security;

class SecurityException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[EnterpriseSecurity] {$message}");
    }
}
