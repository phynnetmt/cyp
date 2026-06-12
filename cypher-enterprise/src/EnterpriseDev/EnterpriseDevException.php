<?php

namespace Cypher\Enterprise\EnterpriseDev;

class EnterpriseDevException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[EnterpriseDev] {$message}");
    }
}
