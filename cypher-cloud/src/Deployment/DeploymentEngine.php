<?php

namespace Cypher\Cloud\Deployment;

class DeploymentEngine
{
    private array $deployments = [];
    private array $config;
    private string $dataDir;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-cloud');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }
        $this->load();
    }

    public function deploy(string $projectDir, array $options = []): Deployment
    {
        $id = uniqid('deploy_', true);
        $startTime = microtime(true);

        $this->validateProject($projectDir);

        $buildId = $this->buildProject($projectDir, $id);
        $snapshot = $this->createSnapshot($projectDir, $id);

        $deployment = new Deployment(
            id: $id,
            project: basename($projectDir),
            status: 'deploying',
            version: $options['version'] ?? date('Y.m.d.Hi'),
            region: $options['region'] ?? 'us-east-1',
            buildId: $buildId,
            snapshot: $snapshot,
            startedAt: date('c'),
        );

        $this->deployments[$id] = $deployment;
        $deployment->status = 'active';
        $deployment->completedAt = date('c');
        $deployment->duration = (microtime(true) - $startTime) * 1000;

        $this->save();
        return $deployment;
    }

    public function rollback(string $deploymentId): Deployment
    {
        $existing = $this->getDeployment($deploymentId);
        if (!$existing) {
            throw new DeploymentException("Deployment '{$deploymentId}' not found");
        }

        $rollbackId = uniqid('rollback_', true);
        $rollback = new Deployment(
            id: $rollbackId,
            project: $existing->project,
            status: 'rolling_back',
            version: $existing->version . '-rollback',
            region: $existing->region,
            buildId: $existing->buildId,
            snapshot: $existing->snapshot,
            startedAt: date('c'),
            parentId: $deploymentId,
        );

        $this->deployments[$rollbackId] = $rollback;
        $rollback->status = 'active';
        $rollback->completedAt = date('c');

        $existing->status = 'rolled_back';
        $this->save();

        return $rollback;
    }

    public function scale(string $deploymentId, int $replicas): Deployment
    {
        $deployment = $this->getDeployment($deploymentId);
        if (!$deployment) {
            throw new DeploymentException("Deployment not found");
        }
        $deployment->replicas = $replicas;
        $deployment->status = 'active';
        $this->save();
        return $deployment;
    }

    public function getDeployment(string $id): ?Deployment
    {
        return $this->deployments[$id] ?? null;
    }

    public function listDeployments(?string $project = null): array
    {
        if ($project) {
            return array_values(array_filter(
                $this->deployments,
                fn($d) => $d->project === $project
            ));
        }
        return array_values($this->deployments);
    }

    public function getActiveDeployments(): array
    {
        return array_values(array_filter(
            $this->deployments,
            fn($d) => $d->status === 'active'
        ));
    }

    public function getDeploymentCount(): int
    {
        return count($this->deployments);
    }

    private function validateProject(string $dir): void
    {
        if (!is_dir($dir)) {
            throw new DeploymentException("Project directory not found: {$dir}");
        }
        if (!file_exists($dir . '/cyp.json') && !file_exists($dir . '/app.cyp')) {
            throw new DeploymentException("No cyp.json or app.cyp found in project. Run 'cyp new' first.");
        }
    }

    private function buildProject(string $dir, string $id): string
    {
        $buildDir = $this->dataDir . '/builds/' . $id;
        if (!is_dir($buildDir)) {
            mkdir($buildDir, 0700, true);
        }

        $this->copyDirectory($dir, $buildDir);
        return $id;
    }

    private function createSnapshot(string $dir, string $id): array
    {
        $snapshot = [
            'id' => $id,
            'files' => [],
            'timestamp' => time(),
        ];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $path = str_replace($dir . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $snapshot['files'][$path] = [
                    'size' => $file->getSize(),
                    'hash' => md5_file($file->getPathname()),
                ];
            }
        }

        return $snapshot;
    }

    private function copyDirectory(string $src, string $dst): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $target = $dst . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            if ($item->isDir()) {
                if (!is_dir($target)) mkdir($target, 0700, true);
            } else {
                copy($item->getPathname(), $target);
            }
        }
    }

    private function load(): void
    {
        $file = $this->dataDir . '/deployments.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                foreach ($data as $item) {
                    $this->deployments[$item['id']] = Deployment::fromArray($item);
                }
            }
        }
    }

    private function save(): void
    {
        $data = array_map(fn($d) => $d->toArray(), $this->deployments);
        file_put_contents(
            $this->dataDir . '/deployments.json',
            json_encode($data, JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
