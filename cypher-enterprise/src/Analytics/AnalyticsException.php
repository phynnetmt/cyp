<?php

namespace Cypher\Enterprise\Analytics;

class AnalyticsException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[Analytics] {$message}");
    }
}
