<?php

namespace Cypher\Runtime\Tools;

class ToolException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[Tools] {$message}");
    }
}
