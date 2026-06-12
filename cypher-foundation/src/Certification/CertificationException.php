<?php

namespace Cypher\Foundation\Certification;

class CertificationException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[Certification] {$message}");
    }
}
