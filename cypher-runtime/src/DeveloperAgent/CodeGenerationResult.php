<?php

namespace Cypher\Runtime\DeveloperAgent;

class CodeGenerationResult
{
    public function __construct(
        public readonly array $files,
        public readonly string $reasoning = '',
        public readonly float $confidence = 0.0,
    ) {}

    public function toArray(): array
    {
        return [
            'files' => $this->files,
            'reasoning' => $this->reasoning,
            'confidence' => $this->confidence,
        ];
    }
}

class CodeReviewResult
{
    public function __construct(
        public readonly string $feedback,
        public readonly string $reasoning = '',
    ) {}
}

class CodeRefactorResult
{
    public function __construct(
        public readonly array $files,
        public readonly string $reasoning = '',
        public readonly array $changes = [],
    ) {}
}

class TestGenerationResult
{
    public function __construct(
        public readonly string $tests,
        public readonly float $coverage = 0.0,
        public readonly string $reasoning = '',
    ) {}
}

class DocumentationResult
{
    public function __construct(
        public readonly string $documentation,
        public readonly string $reasoning = '',
    ) {}
}

class BugFixResult
{
    public function __construct(
        public readonly array $files,
        public readonly string $fixDescription = '',
        public readonly float $confidence = 0.0,
    ) {}
}
