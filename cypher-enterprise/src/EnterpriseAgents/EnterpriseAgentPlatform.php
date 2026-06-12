<?php

namespace Cypher\Enterprise\EnterpriseAgents;

class EnterpriseAgentPlatform
{
    private array $departments = [];
    private array $knowledgeNetworks = [];
    private array $privateClusters = [];
    private string $dataDir;

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-enterprise/agents');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }
        $this->load();
    }

    public function createDepartment(string $name, string $orgId, array $config = []): array
    {
        $id = uniqid('dept_', true);
        $dept = [
            'id' => $id,
            'name' => $name,
            'org_id' => $orgId,
            'agent_count' => 0,
            'config' => $config,
            'status' => 'active',
            'created_at' => date('c'),
        ];
        $this->departments[$id] = $dept;
        $this->save();
        return $dept;
    }

    public function createKnowledgeNetwork(string $name, string $departmentId, array $sources = []): array
    {
        $id = uniqid('kn_', true);
        $network = [
            'id' => $id,
            'name' => $name,
            'department_id' => $departmentId,
            'sources' => $sources,
            'document_count' => 0,
            'status' => 'active',
            'created_at' => date('c'),
        ];
        $this->knowledgeNetworks[$id] = $network;
        $this->save();
        return $network;
    }

    public function indexDocument(string $networkId, string $content, array $metadata = []): string
    {
        $network = $this->knowledgeNetworks[$networkId] ?? null;
        if (!$network) {
            throw new EnterpriseAgentException("Knowledge network not found");
        }

        $docId = uniqid('doc_', true);
        $this->knowledgeNetworks[$networkId]['document_count']++;
        $this->save();

        return $docId;
    }

    public function searchKnowledge(string $networkId, string $query, int $limit = 5): array
    {
        $network = $this->knowledgeNetworks[$networkId] ?? null;
        if (!$network) {
            throw new EnterpriseAgentException("Knowledge network not found");
        }

        return [
            'query' => $query,
            'results' => [
                ['content' => "Result for: {$query}", 'relevance' => 0.95],
                ['content' => "Related information to: {$query}", 'relevance' => 0.75],
            ],
            'total' => $this->knowledgeNetworks[$networkId]['document_count'],
        ];
    }

    public function createPrivateCluster(string $name, string $orgId, string $agentType, int $replicas = 1): array
    {
        $id = uniqid('pc_', true);
        $cluster = [
            'id' => $id,
            'name' => $name,
            'org_id' => $orgId,
            'agent_type' => $agentType,
            'replicas' => $replicas,
            'status' => 'active',
            'created_at' => date('c'),
        ];
        $this->privateClusters[$id] = $cluster;
        $this->save();
        return $cluster;
    }

    public function listDepartments(string $orgId = ''): array
    {
        if ($orgId) {
            return array_values(array_filter($this->departments, fn($d) => $d['org_id'] === $orgId));
        }
        return array_values($this->departments);
    }

    public function listKnowledgeNetworks(string $departmentId = ''): array
    {
        if ($departmentId) {
            return array_values(array_filter($this->knowledgeNetworks, fn($n) => $n['department_id'] === $departmentId));
        }
        return array_values($this->knowledgeNetworks);
    }

    public function listPrivateClusters(string $orgId = ''): array
    {
        if ($orgId) {
            return array_values(array_filter($this->privateClusters, fn($c) => $c['org_id'] === $orgId));
        }
        return array_values($this->privateClusters);
    }

    public function getStats(): array
    {
        return [
            'departments' => count($this->departments),
            'knowledge_networks' => count($this->knowledgeNetworks),
            'total_documents' => array_sum(array_column($this->knowledgeNetworks, 'document_count')),
            'private_clusters' => count($this->privateClusters),
        ];
    }

    private function load(): void
    {
        $file = $this->dataDir . '/enterprise_agents.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->departments = $data['departments'] ?? [];
                $this->knowledgeNetworks = $data['knowledge_networks'] ?? [];
                $this->privateClusters = $data['private_clusters'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/enterprise_agents.json',
            json_encode([
                'departments' => $this->departments,
                'knowledge_networks' => $this->knowledgeNetworks,
                'private_clusters' => $this->privateClusters,
            ], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
