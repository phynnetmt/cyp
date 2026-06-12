<?php

namespace Cypher\Runtime\Reasoning;

class ReasoningEngine
{
    private array $config;
    private array $strategies;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->strategies = [
            'direct' => new DirectReasoning(),
            'cot' => new ChainOfThoughtReasoning(),
            'tot' => new TreeOfThoughtReasoning(),
        ];
    }

    public function reason(string $input, array $context = []): ReasoningResult
    {
        $strategy = $this->config['strategy'] ?? 'cot';
        $engine = $this->strategies[$strategy] ?? $this->strategies['direct'];

        return $engine->reason($input, $context, $this->config);
    }

    public function addStrategy(string $name, ReasoningStrategy $strategy): void
    {
        $this->strategies[$name] = $strategy;
    }

    public function getAvailableStrategies(): array
    {
        return array_keys($this->strategies);
    }
}
