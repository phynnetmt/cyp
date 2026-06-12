<?php

namespace Cypher\Runtime\Workflow;

class WorkflowException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[Workflow] {$message}");
    }
}
