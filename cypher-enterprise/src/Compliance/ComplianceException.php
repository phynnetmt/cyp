<?php

namespace Cypher\Enterprise\Compliance;

class ComplianceException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[Compliance] {$message}");
    }
}
