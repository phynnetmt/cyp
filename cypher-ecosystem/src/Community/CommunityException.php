<?php

namespace Cypher\Ecosystem\Community;

class CommunityException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("[Community] {$message}");
    }
}
