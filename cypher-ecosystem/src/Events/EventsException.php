<?php

namespace Cypher\Ecosystem\Events;

class EventsException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[Events] {$message}");
    }
}
