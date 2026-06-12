<?php

namespace Cypher\Cloud\Security;

class SecurityPlatform
{
    private array $secrets = [];
    private array $roles = [];
    private array $auditLog = [];
    private string $dataDir;

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-cloud/security');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }

        $this->roles = [
            'admin' => ['permissions' => ['*']],
            'developer' => ['permissions' => ['deploy', 'view_logs', 'manage_services']],
            'viewer' => ['permissions' => ['view_logs', 'view_metrics']],
        ];

        $this->load();
    }

    public function storeSecret(string $name, string $value): string
    {
        $id = uniqid('sec_', true);
        $encrypted = $this->encrypt($value);

        $this->secrets[$name] = [
            'id' => $id,
            'name' => $name,
            'encrypted_value' => $encrypted,
            'created_at' => date('c'),
            'version' => ($this->secrets[$name]['version'] ?? 0) + 1,
        ];

        $this->audit('secret.created', ['name' => $name]);
        $this->save();

        return $id;
    }

    public function getSecret(string $name): ?string
    {
        $secret = $this->secrets[$name] ?? null;
        if (!$secret) return null;

        $this->audit('secret.access', ['name' => $name]);
        return $this->decrypt($secret['encrypted_value']);
    }

    public function listSecrets(): array
    {
        return array_map(fn($s) => [
            'name' => $s['name'],
            'created_at' => $s['created_at'],
            'version' => $s['version'],
        ], array_values($this->secrets));
    }

    public function deleteSecret(string $name): void
    {
        unset($this->secrets[$name]);
        $this->audit('secret.deleted', ['name' => $name]);
        $this->save();
    }

    public function hasPermission(string $role, string $permission): bool
    {
        $roleDef = $this->roles[$role] ?? null;
        if (!$roleDef) return false;
        return in_array('*', $roleDef['permissions']) || in_array($permission, $roleDef['permissions']);
    }

    public function createRole(string $name, array $permissions): void
    {
        $this->roles[$name] = ['permissions' => $permissions];
        $this->audit('role.created', ['name' => $name]);
        $this->save();
    }

    public function listRoles(): array
    {
        return $this->roles;
    }

    public function getAuditLog(int $limit = 50): array
    {
        return array_slice(array_reverse($this->auditLog), 0, $limit);
    }

    public function getStats(): array
    {
        return [
            'secrets' => count($this->secrets),
            'roles' => count($this->roles),
            'audit_entries' => count($this->auditLog),
        ];
    }

    private function encrypt(string $value): string
    {
        $key = $this->getEncryptionKey();
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($value, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }

    private function decrypt(string $payload): string
    {
        $key = $this->getEncryptionKey();
        $data = base64_decode($payload);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    }

    private function getEncryptionKey(): string
    {
        $keyFile = $this->dataDir . '/.encryption_key';
        if (!file_exists($keyFile)) {
            $key = random_bytes(32);
            file_put_contents($keyFile, base64_encode($key), LOCK_EX);
        }
        return base64_decode(file_get_contents($keyFile));
    }

    private function audit(string $action, array $data): void
    {
        $this->auditLog[] = [
            'action' => $action,
            'data' => $data,
            'timestamp' => date('c'),
        ];
    }

    private function load(): void
    {
        $file = $this->dataDir . '/security.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->secrets = $data['secrets'] ?? [];
                $this->auditLog = $data['audit_log'] ?? [];
                $this->roles = $data['roles'] ?? $this->roles;
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/security.json',
            json_encode([
                'secrets' => $this->secrets,
                'audit_log' => $this->auditLog,
                'roles' => $this->roles,
            ], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
