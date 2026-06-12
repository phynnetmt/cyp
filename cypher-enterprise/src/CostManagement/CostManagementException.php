<?php

namespace Cypher\Enterprise\CostManagement;

class CostManagementException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[CostManagement] {$message}");
    }
}
