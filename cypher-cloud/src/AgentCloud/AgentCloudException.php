<?php

namespace Cypher\Cloud\AgentCloud;

class AgentCloudException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[AgentCloud] {$message}");
    }
}
