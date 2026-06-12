<?php

namespace Cypher\Runtime\Workflow;

class WorkflowResult
{
    public function __construct(
        public readonly string $executionId,
        public readonly string $workflowName,
        public readonly array $stepResults,
        public readonly string $status,
        public readonly ?string $error = null,
    ) {}

    public function isSuccess(): bool
    {
        return $this->status === 'completed';
    }

    public function toArray(): array
    {
        return [
            'execution_id' => $this->executionId,
            'workflow' => $this->workflowName,
            'status' => $this->status,
            'steps' => $this->stepResults,
            'error' => $this->error,
        ];
    }
}
