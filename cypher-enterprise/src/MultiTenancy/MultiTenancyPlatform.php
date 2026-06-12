<?php

namespace Cypher\Enterprise\MultiTenancy;

class MultiTenancyPlatform
{
    private array $organizations = [];
    private array $workspaces = [];
    private string $dataDir;

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-enterprise/tenancy');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }
        $this->load();
    }

    public function createOrganization(string $name, string $ownerEmail, array $config = []): array
    {
        $id = uniqid('org_', true);
        $org = [
            'id' => $id,
            'name' => $name,
            'owner' => $ownerEmail,
            'status' => 'active',
            'config' => $config,
            'workspace_count' => 0,
            'member_count' => 0,
            'created_at' => date('c'),
        ];
        $this->organizations[$id] = $org;
        $this->save();
        return $org;
    }

    public function getOrganization(string $id): ?array
    {
        return $this->organizations[$id] ?? null;
    }

    public function listOrganizations(): array
    {
        return array_values($this->organizations);
    }

    public function createWorkspace(string $name, string $orgId, array $config = []): array
    {
        $org = $this->getOrganization($orgId);
        if (!$org) {
            throw new MultiTenancyException("Organization not found: {$orgId}");
        }

        $id = uniqid('ws_', true);
        $workspace = [
            'id' => $id,
            'name' => $name,
            'org_id' => $orgId,
            'status' => 'active',
            'config' => $config,
            'created_at' => date('c'),
        ];

        $this->workspaces[$id] = $workspace;
        $this->organizations[$orgId]['workspace_count']++;
        $this->save();

        return $workspace;
    }

    public function getWorkspace(string $id): ?array
    {
        return $this->workspaces[$id] ?? null;
    }

    public function listWorkspaces(string $orgId = ''): array
    {
        if ($orgId) {
            return array_values(array_filter($this->workspaces, fn($w) => $w['org_id'] === $orgId));
        }
        return array_values($this->workspaces);
    }

    public function addMember(string $orgId, string $userEmail, string $role = 'member'): void
    {
        if (!isset($this->organizations[$orgId])) {
            throw new MultiTenancyException("Organization not found");
        }
        if (!isset($this->organizations[$orgId]['members'])) {
            $this->organizations[$orgId]['members'] = [];
        }
        $this->organizations[$orgId]['members'][] = [
            'email' => $userEmail,
            'role' => $role,
            'joined_at' => date('c'),
        ];
        $this->organizations[$orgId]['member_count'] = count($this->organizations[$orgId]['members']);
        $this->save();
    }

    public function removeMember(string $orgId, string $userEmail): void
    {
        if (!isset($this->organizations[$orgId]['members'])) return;
        $this->organizations[$orgId]['members'] = array_values(array_filter(
            $this->organizations[$orgId]['members'],
            fn($m) => $m['email'] !== $userEmail
        ));
        $this->organizations[$orgId]['member_count'] = count($this->organizations[$orgId]['members']);
        $this->save();
    }

    public function listMembers(string $orgId): array
    {
        return $this->organizations[$orgId]['members'] ?? [];
    }

    public function getStats(): array
    {
        return [
            'organizations' => count($this->organizations),
            'workspaces' => count($this->workspaces),
            'total_members' => array_sum(array_map(fn($o) => $o['member_count'] ?? 0, $this->organizations)),
        ];
    }

    public function deactivateOrganization(string $id): void
    {
        if (!isset($this->organizations[$id])) {
            throw new MultiTenancyException("Organization not found");
        }
        $this->organizations[$id]['status'] = 'inactive';
        $this->save();
    }

    private function load(): void
    {
        $file = $this->dataDir . '/tenancy.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->organizations = $data['organizations'] ?? [];
                $this->workspaces = $data['workspaces'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/tenancy.json',
            json_encode(['organizations' => $this->organizations, 'workspaces' => $this->workspaces], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
