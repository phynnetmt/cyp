<?php

namespace Cypher\Cloud\ManagedServices;

class ManagedServicesException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[ManagedServices] {$message}");
    }
}
