<?php

namespace Cypher\Enterprise\Governance;

class GovernancePlatform
{
    private array $policies = [];
    private array $approvalWorkflows = [];
    private array $pendingApprovals = [];
    private string $dataDir;

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-enterprise/governance');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }
        $this->load();
    }

    public function createPolicy(string $name, string $type, array $rules, string $orgId = ''): string
    {
        $id = uniqid('pol_', true);
        $this->policies[$id] = [
            'id' => $id,
            'name' => $name,
            'type' => $type,
            'rules' => $rules,
            'org_id' => $orgId,
            'status' => 'active',
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];
        $this->save();
        return $id;
    }

    public function evaluate(string $policyType, array $context, string $orgId = ''): PolicyEvaluation
    {
        $relevant = array_filter($this->policies, fn($p) =>
            $p['type'] === $policyType && $p['status'] === 'active' &&
            (!$p['org_id'] || $p['org_id'] === $orgId)
        );

        $allowed = true;
        $reasons = [];

        foreach ($relevant as $policy) {
            foreach ($policy['rules'] as $rule) {
                $result = $this->evaluateRule($rule, $context);
                if (!$result['allowed']) {
                    $allowed = false;
                    $reasons[] = "Policy '{$policy['name']}': {$result['reason']}";
                }
            }
        }

        return new PolicyEvaluation($allowed, $reasons);
    }

    public function createApprovalWorkflow(string $name, string $resourceType, array $steps): string
    {
        $id = uniqid('wf_', true);
        $this->approvalWorkflows[$id] = [
            'id' => $id,
            'name' => $name,
            'resource_type' => $resourceType,
            'steps' => $steps,
            'status' => 'active',
            'created_at' => date('c'),
        ];
        $this->save();
        return $id;
    }

    public function requestApproval(string $workflowId, string $resourceId, string $requestedBy, array $context = []): string
    {
        $wf = $this->approvalWorkflows[$workflowId] ?? null;
        if (!$wf) {
            throw new GovernanceException("Approval workflow not found");
        }

        $id = uniqid('apr_', true);
        $this->pendingApprovals[$id] = [
            'id' => $id,
            'workflow_id' => $workflowId,
            'resource_id' => $resourceId,
            'requested_by' => $requestedBy,
            'context' => $context,
            'status' => 'pending',
            'current_step' => 0,
            'steps' => $wf['steps'],
            'created_at' => date('c'),
        ];
        $this->save();
        return $id;
    }

    public function approve(string $approvalId, string $approver, ?string $comment = null): array
    {
        $approval = $this->pendingApprovals[$approvalId] ?? null;
        if (!$approval) {
            throw new GovernanceException("Approval request not found");
        }

        $approval['current_step']++;
        $approval['steps'][$approval['current_step'] - 1] = [
            'approver' => $approver,
            'comment' => $comment,
            'action' => 'approved',
            'timestamp' => date('c'),
        ];

        if ($approval['current_step'] >= count($approval['steps'])) {
            $approval['status'] = 'approved';
        }

        $this->pendingApprovals[$approvalId] = $approval;
        $this->save();
        return $approval;
    }

    public function reject(string $approvalId, string $approver, string $reason): array
    {
        $approval = $this->pendingApprovals[$approvalId] ?? null;
        if (!$approval) {
            throw new GovernanceException("Approval request not found");
        }

        $approval['status'] = 'rejected';
        $approval['steps'][] = [
            'approver' => $approver,
            'reason' => $reason,
            'action' => 'rejected',
            'timestamp' => date('c'),
        ];

        $this->pendingApprovals[$approvalId] = $approval;
        $this->save();
        return $approval;
    }

    public function listPolicies(string $orgId = ''): array
    {
        if ($orgId) {
            return array_values(array_filter($this->policies, fn($p) => $p['org_id'] === $orgId));
        }
        return array_values($this->policies);
    }

    public function listPendingApprovals(): array
    {
        return array_values(array_filter($this->pendingApprovals, fn($a) => $a['status'] === 'pending'));
    }

    public function getPolicy(string $id): ?array
    {
        return $this->policies[$id] ?? null;
    }

    private function evaluateRule(array $rule, array $context): array
    {
        $field = $rule['field'] ?? '';
        $operator = $rule['operator'] ?? 'equals';
        $value = $rule['value'] ?? null;
        $actual = $context[$field] ?? null;

        return match ($operator) {
            'equals' => [
                'allowed' => $actual === $value,
                'reason' => $actual === $value ? '' : "{$field} must equal {$value}, got {$actual}",
            ],
            'not_equals' => [
                'allowed' => $actual !== $value,
                'reason' => $actual !== $value ? '' : "{$field} must not equal {$value}",
            ],
            'gt' => [
                'allowed' => is_numeric($actual) && $actual > $value,
                'reason' => is_numeric($actual) && $actual > $value ? '' : "{$field} must be greater than {$value}",
            ],
            'lt' => [
                'allowed' => is_numeric($actual) && $actual < $value,
                'reason' => is_numeric($actual) && $actual < $value ? '' : "{$field} must be less than {$value}",
            ],
            'in' => [
                'allowed' => is_array($value) && in_array($actual, $value),
                'reason' => is_array($value) && in_array($actual, $value) ? '' : "{$field} must be one of: " . implode(', ', $value ?? []),
            ],
            'contains' => [
                'allowed' => is_string($actual) && str_contains($actual, $value ?? ''),
                'reason' => is_string($actual) && str_contains($actual, $value ?? '') ? '' : "{$field} must contain {$value}",
            ],
            default => ['allowed' => true, 'reason' => ''],
        };
    }

    private function load(): void
    {
        $file = $this->dataDir . '/governance.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->policies = $data['policies'] ?? [];
                $this->approvalWorkflows = $data['workflows'] ?? [];
                $this->pendingApprovals = $data['approvals'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/governance.json',
            json_encode([
                'policies' => $this->policies,
                'workflows' => $this->approvalWorkflows,
                'approvals' => $this->pendingApprovals,
            ], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
