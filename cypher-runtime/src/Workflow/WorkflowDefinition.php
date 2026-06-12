<?php

namespace Cypher\Runtime\Workflow;

class WorkflowDefinition
{
    private array $steps;
    private string $name;
    private array $metadata = [];

    public function __construct(string $name, array $steps = [])
    {
        $this->name = $name;
        $this->steps = $steps;
    }

    public function addStep(array $step): self
    {
        $step['id'] = $step['id'] ?? uniqid('step_');
        $this->steps[] = $step;
        return $this;
    }

    public function agent(string $agentName, string $input, array $options = []): self
    {
        return $this->addStep(array_merge([
            'type' => 'agent',
            'agent' => $agentName,
            'input' => $input,
        ], $options));
    }

    public function action(string $action, array $params = [], array $options = []): self
    {
        return $this->addStep(array_merge([
            'type' => 'action',
            'action' => $action,
            'params' => $params,
        ], $options));
    }

    public function condition(string $condition, ?string $ifTrue = null, ?string $ifFalse = null): self
    {
        return $this->addStep([
            'type' => 'condition',
            'condition' => $condition,
            'if_true' => $ifTrue,
            'if_false' => $ifFalse,
        ]);
    }

    public function delay(int $seconds): self
    {
        return $this->addStep([
            'type' => 'delay',
            'seconds' => $seconds,
        ]);
    }

    public function parallel(array $steps): self
    {
        return $this->addStep([
            'type' => 'parallel',
            'steps' => $steps,
        ]);
    }

    public function getSteps(): array
    {
        return $this->steps;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
