<?php

namespace Cypher\Foundation\Finance;

class FinanceException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[Finance] {$message}");
    }
}
