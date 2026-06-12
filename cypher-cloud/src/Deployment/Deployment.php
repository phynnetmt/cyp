<?php

namespace Cypher\Cloud\Deployment;

class Deployment
{
    public function __construct(
        public readonly string $id,
        public readonly string $project,
        public string $status = 'pending',
        public string $version = '',
        public string $region = 'us-east-1',
        public ?string $buildId = null,
        public array $snapshot = [],
        public ?string $startedAt = null,
        public ?string $completedAt = null,
        public ?float $duration = null,
        public int $replicas = 1,
        public ?string $parentId = null,
        public array $domains = [],
        public array $environment = [],
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'project' => $this->project,
            'status' => $this->status,
            'version' => $this->version,
            'region' => $this->region,
            'build_id' => $this->buildId,
            'snapshot' => $this->snapshot,
            'started_at' => $this->startedAt,
            'completed_at' => $this->completedAt,
            'duration' => $this->duration,
            'replicas' => $this->replicas,
            'parent_id' => $this->parentId,
            'domains' => $this->domains,
            'environment' => $this->environment,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            project: $data['project'],
            status: $data['status'] ?? 'pending',
            version: $data['version'] ?? '',
            region: $data['region'] ?? 'us-east-1',
            buildId: $data['build_id'] ?? null,
            snapshot: $data['snapshot'] ?? [],
            startedAt: $data['started_at'] ?? null,
            completedAt: $data['completed_at'] ?? null,
            duration: $data['duration'] ?? null,
            replicas: $data['replicas'] ?? 1,
            parentId: $data['parent_id'] ?? null,
            domains: $data['domains'] ?? [],
            environment: $data['environment'] ?? [],
        );
    }
}
