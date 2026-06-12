<?php

namespace Cypher\Cloud\Security;

class SecurityException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[Security] {$message}");
    }
}
