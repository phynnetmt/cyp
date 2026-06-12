<?php

namespace Cypher\Enterprise\Certification;

class CertificationException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[Certification] {$message}");
    }
}
