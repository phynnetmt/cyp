<?php

namespace Cypher\Cloud\Platform;

class CloudException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[CypherCloud] {$message}");
    }
}
