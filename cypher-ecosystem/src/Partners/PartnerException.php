<?php

namespace Cypher\Ecosystem\Partners;

class PartnerException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[PartnerEcosystem] {$message}");
    }
}
