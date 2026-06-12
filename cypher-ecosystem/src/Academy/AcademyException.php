<?php

namespace Cypher\Ecosystem\Academy;

class AcademyException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[Academy] {$message}");
    }
}
