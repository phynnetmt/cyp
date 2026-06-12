<?php

namespace Cypher\Enterprise\Identity;

class IdentityPlatform
{
    private array $users = [];
    private array $roles = [];
    private array $sessions = [];
    private array $mfaDevices = [];
    private array $directoryConnections = [];
    private string $dataDir;

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-enterprise/identity');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }

        $this->roles = [
            'super_admin' => ['permissions' => ['*'], 'priority' => 100],
            'org_admin' => ['permissions' => ['manage_users', 'manage_org', 'deploy', 'audit'], 'priority' => 80],
            'developer' => ['permissions' => ['deploy', 'view_logs', 'manage_agents'], 'priority' => 50],
            'viewer' => ['permissions' => ['view_logs', 'view_metrics'], 'priority' => 20],
        ];

        $this->load();
    }

    public function createUser(string $email, string $name, string $role = 'developer', string $orgId = ''): array
    {
        if (isset($this->users[$email])) {
            throw new IdentityException("User '{$email}' already exists");
        }

        $id = uniqid('user_', true);
        $user = [
            'id' => $id,
            'email' => $email,
            'name' => $name,
            'role' => $role,
            'org_id' => $orgId,
            'mfa_enabled' => false,
            'status' => 'active',
            'created_at' => date('c'),
            'last_login' => null,
            'metadata' => [],
        ];

        $this->users[$email] = $user;
        $this->save();
        return $user;
    }

    public function getUser(string $email): ?array
    {
        return $this->users[$email] ?? null;
    }

    public function getUserById(string $id): ?array
    {
        foreach ($this->users as $user) {
            if ($user['id'] === $id) return $user;
        }
        return null;
    }

    public function listUsers(string $orgId = ''): array
    {
        if ($orgId) {
            return array_values(array_filter($this->users, fn($u) => $u['org_id'] === $orgId));
        }
        return array_values($this->users);
    }

    public function deactivateUser(string $email): void
    {
        if (!isset($this->users[$email])) {
            throw new IdentityException("User not found: {$email}");
        }
        $this->users[$email]['status'] = 'inactive';
        $this->save();
    }

    public function assignRole(string $email, string $role): void
    {
        if (!isset($this->roles[$role])) {
            throw new IdentityException("Role '{$role}' not found");
        }
        if (!isset($this->users[$email])) {
            throw new IdentityException("User not found: {$email}");
        }
        $this->users[$email]['role'] = $role;
        $this->save();
    }

    public function createRole(string $name, array $permissions, int $priority = 50): void
    {
        $this->roles[$name] = ['permissions' => $permissions, 'priority' => $priority];
        $this->save();
    }

    public function getRole(string $name): ?array
    {
        return $this->roles[$name] ?? null;
    }

    public function listRoles(): array
    {
        return $this->roles;
    }

    public function hasPermission(string $userEmail, string $permission): bool
    {
        $user = $this->getUser($userEmail);
        if (!$user || $user['status'] !== 'active') return false;

        $role = $this->roles[$user['role']] ?? null;
        if (!$role) return false;

        return in_array('*', $role['permissions']) || in_array($permission, $role['permissions']);
    }

    public function enableMFA(string $email, string $deviceType = 'totp'): string
    {
        if (!isset($this->users[$email])) {
            throw new IdentityException("User not found: {$email}");
        }

        $secret = bin2hex(random_bytes(20));
        $deviceId = uniqid('mfa_', true);

        $this->mfaDevices[$deviceId] = [
            'id' => $deviceId,
            'user_email' => $email,
            'type' => $deviceType,
            'secret' => $secret,
            'enabled' => true,
            'created_at' => date('c'),
        ];

        $this->users[$email]['mfa_enabled'] = true;
        $this->save();

        return $secret;
    }

    public function verifyMFA(string $email, string $code): bool
    {
        foreach ($this->mfaDevices as $device) {
            if ($device['user_email'] === $email && $device['enabled']) {
                $expected = hash('sha256', $device['secret'] . substr(date('Y-m-d-H'), 0, 13));
                return hash_equals($expected, hash('sha256', $device['secret'] . $code));
            }
        }
        return false;
    }

    public function authenticate(string $email, string $password, ?string $mfaCode = null): bool
    {
        $user = $this->getUser($email);
        if (!$user || $user['status'] !== 'active') return false;

        $storedHash = $user['password_hash'] ?? null;
        if (!$storedHash) return false;

        if (!password_verify($password, $storedHash)) return false;

        if ($user['mfa_enabled'] && $mfaCode) {
            if (!$this->verifyMFA($email, $mfaCode)) return false;
        }

        $this->users[$email]['last_login'] = date('c');
        $this->save();
        return true;
    }

    public function setPassword(string $email, string $password): void
    {
        if (!isset($this->users[$email])) {
            throw new IdentityException("User not found: {$email}");
        }
        $this->users[$email]['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
        $this->save();
    }

    public function connectDirectory(string $name, string $type, array $config = []): string
    {
        $id = uniqid('dir_', true);
        $this->directoryConnections[$id] = [
            'id' => $id,
            'name' => $name,
            'type' => $type,
            'config' => $config,
            'status' => 'connected',
            'last_sync' => null,
            'created_at' => date('c'),
        ];
        $this->save();
        return $id;
    }

    public function listDirectoryConnections(): array
    {
        return array_values($this->directoryConnections);
    }

    public function getUsersByRole(string $role): array
    {
        return array_values(array_filter($this->users, fn($u) => $u['role'] === $role));
    }

    private function load(): void
    {
        $file = $this->dataDir . '/identity.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->users = $data['users'] ?? [];
                $this->roles = $data['roles'] ?? $this->roles;
                $this->mfaDevices = $data['mfa_devices'] ?? [];
                $this->directoryConnections = $data['directories'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/identity.json',
            json_encode([
                'users' => $this->users,
                'roles' => $this->roles,
                'mfa_devices' => $this->mfaDevices,
                'directories' => $this->directoryConnections,
            ], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
