<?php

namespace Cypher\Enterprise\Identity;

class IdentityException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[Identity] {$message}");
    }
}
