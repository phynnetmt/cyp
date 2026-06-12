<?php

namespace Cypher\RuntimeEngine\Http;

class HttpException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[HttpRuntime] {$message}");
    }
}
