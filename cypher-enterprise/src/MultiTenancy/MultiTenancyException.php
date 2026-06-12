<?php

namespace Cypher\Enterprise\MultiTenancy;

class MultiTenancyException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[MultiTenancy] {$message}");
    }
}
