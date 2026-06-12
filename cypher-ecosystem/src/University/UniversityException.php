<?php

namespace Cypher\Ecosystem\University;

class UniversityException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[UniversityProgram] {$message}");
    }
}
