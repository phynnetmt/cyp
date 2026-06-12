<?php

namespace Cypher\Runtime\Reasoning;

interface ReasoningStrategy
{
    public function reason(string $input, array $context, array $config): ReasoningResult;
}
