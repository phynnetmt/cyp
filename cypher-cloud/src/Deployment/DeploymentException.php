<?php

namespace Cypher\Cloud\Deployment;

class DeploymentException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[Deployment] {$message}");
    }
}
