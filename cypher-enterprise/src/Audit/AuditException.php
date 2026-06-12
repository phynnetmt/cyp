<?php

namespace Cypher\Enterprise\Audit;

class AuditException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[Audit] {$message}");
    }
}
