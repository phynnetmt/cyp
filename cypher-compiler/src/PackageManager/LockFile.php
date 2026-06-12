<?php

namespace Cypher\Compiler\PackageManager;

class LockFile
{
    private array $packages = [];
    private string $path = '';
    private string $contentHash = '';

    public static function load(string $path): self
    {
        $lock = new self();
        $lock->path = $path;

        if (!file_exists($path)) {
            return $lock;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new PackageManagerException("Cannot read lock file: {$path}");
        }

        $data = json_decode($content, true);
        if ($data === null && trim($content) !== '') {
            throw new PackageManagerException("Invalid JSON in lock file: {$path}");
        }

        if ($data !== null) {
            $lock->packages = $data['packages'] ?? [];
            $lock->contentHash = $data['content-hash'] ?? '';
        }

        return $lock;
    }

    public function addPackage(string $name, string $version, string $constraint, array $dependencies = []): void
    {
        $this->packages[$name] = [
            'version' => $version,
            'constraint' => $constraint,
            'dependencies' => $dependencies,
        ];
    }

    public function getPackage(string $name): ?array
    {
        return $this->packages[$name] ?? null;
    }

    public function hasPackage(string $name): bool
    {
        return isset($this->packages[$name]);
    }

    public function removePackage(string $name): void
    {
        unset($this->packages[$name]);
    }

    public function getPackages(): array
    {
        return $this->packages;
    }

    public function setContentHash(string $hash): void
    {
        $this->contentHash = $hash;
    }

    public function getContentHash(): string
    {
        return $this->contentHash;
    }

    public function save(?string $path = null): void
    {
        $path = $path ?? $this->path;
        if (empty($path)) {
            $path = getcwd() . DIRECTORY_SEPARATOR . 'cyp.lock';
        }

        $data = [
            'lockfileVersion' => 1,
            'content-hash' => $this->contentHash,
            'packages' => $this->packages,
        ];

        $tmp = $path . '.' . uniqid('', true) . '.tmp';
        $written = @file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        if ($written === false) {
            throw new PackageManagerException("Failed to write lock file: {$path}");
        }
        if (!rename($tmp, $path)) {
            unlink($tmp);
            throw new PackageManagerException("Failed to rename temporary lock file");
        }

        $this->path = $path;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
