<?php

namespace Cypher\RuntimeEngine\Sandbox;

class PackageValidation
{
    public function __construct(
        public readonly string $packageName,
        public readonly string $version,
        public readonly bool $passed,
        public readonly array $issues = [],
    ) {}

    public function toArray(): array
    {
        return [
            'package' => $this->packageName,
            'version' => $this->version,
            'passed' => $this->passed,
            'issues' => $this->issues,
        ];
    }
}
