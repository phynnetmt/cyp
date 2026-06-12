<?php

namespace Cypher\RuntimeEngine\Sandbox;

class SecuritySandbox
{
    private array $permissions = [];
    private array $allowedOperations = [];
    private array $deniedOperations = [];
    private bool $active = true;

    public function __construct(array $config = [])
    {
        $this->allowedOperations = $config['allowed'] ?? ['*'];
        $this->deniedOperations = $config['denied'] ?? [];
        $this->permissions = $config['permissions'] ?? [
            'filesystem_read' => true,
            'filesystem_write' => false,
            'network' => false,
            'exec' => false,
            'env_read' => true,
        ];
    }

    public function check(string $operation): bool
    {
        if (!$this->active) return true;

        // Deny list has priority
        if (in_array($operation, $this->deniedOperations)) return false;
        if (in_array('*', $this->deniedOperations)) return false;

        // Check allow list
        if (!in_array('*', $this->allowedOperations) && !in_array($operation, $this->allowedOperations)) {
            return false;
        }

        return true;
    }

    public function checkPermission(string $permission): bool
    {
        return $this->permissions[$permission] ?? false;
    }

    public function setPermission(string $permission, bool $value): void
    {
        $this->permissions[$permission] = $value;
    }

    public function allow(string $operation): void
    {
        $this->allowedOperations[] = $operation;
    }

    public function deny(string $operation): void
    {
        $this->deniedOperations[] = $operation;
    }

    public function disable(): void
    {
        $this->active = false;
    }

    public function enable(): void
    {
        $this->active = true;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function validatePackage(string $packageName, string $version, string $content = ''): PackageValidation
    {
        $issues = [];

        if (str_contains($content, 'exec(') || str_contains($content, 'shell_exec(') || str_contains($content, 'system(')) {
            $issues[] = ['severity' => 'critical', 'message' => 'Package uses dangerous function', 'line' => 0];
        }

        if (str_contains($content, 'base64_decode(')) {
            $issues[] = ['severity' => 'medium', 'message' => 'Package uses obfuscation techniques', 'line' => 0];
        }

        $passed = empty($issues);

        return new PackageValidation(
            packageName: $packageName,
            version: $version,
            passed: $passed,
            issues: $issues,
        );
    }

    public function getConfig(): array
    {
        return [
            'active' => $this->active,
            'permissions' => $this->permissions,
            'allowed_operations' => $this->allowedOperations,
            'denied_operations' => $this->deniedOperations,
        ];
    }
}
