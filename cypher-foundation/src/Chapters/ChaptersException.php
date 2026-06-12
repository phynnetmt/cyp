<?php

namespace Cypher\Foundation\Chapters;

class ChaptersException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[Chapters] {$message}");
    }
}
