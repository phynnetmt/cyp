<?php

namespace Cypher\Foundation\Governance;

class GovernanceFramework
{
    private array $committees = [];
    private array $councils = [];
    private array $proposals = [];
    private array $votes = [];
    private string $dataDir;

    private const COMMITTEE_TYPES = [
        'technical_steering' => [
            'name' => 'Technical Steering Committee',
            'responsibilities' => ['Language Evolution', 'Technical Standards', 'Compiler Roadmaps', 'Runtime Architecture'],
            'max_members' => 9,
        ],
        'ecosystem_council' => [
            'name' => 'Ecosystem Council',
            'responsibilities' => ['Community Programs', 'Marketplace Standards', 'Partner Relations', 'Developer Experience'],
            'max_members' => 7,
        ],
        'security_council' => [
            'name' => 'Security Council',
            'responsibilities' => ['Security Standards', 'Incident Response', 'Package Verification', 'Runtime Security'],
            'max_members' => 5,
        ],
    ];

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-foundation/governance');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }

        foreach (self::COMMITTEE_TYPES as $key => $def) {
            $this->committees[$key] = $def;
            $this->committees[$key]['members'] = [];
        }

        $this->load();
    }

    public function appointToCommittee(string $committeeType, string $name, string $role = 'member'): array
    {
        if (!isset($this->committees[$committeeType])) {
            throw new GovernanceException("Unknown committee: {$committeeType}");
        }

        $committee = &$this->committees[$committeeType];
        if (count($committee['members']) >= $committee['max_members']) {
            throw new GovernanceException("Committee '{$committeeType}' is full");
        }

        $id = uniqid('cmte_', true);
        $member = [
            'id' => $id,
            'name' => $name,
            'role' => $role,
            'appointed_at' => date('c'),
        ];

        $committee['members'][] = $member;
        $this->save();
        return $member;
    }

    public function submitGovernanceProposal(string $title, string $author, string $summary, string $details): array
    {
        $id = uniqid('gprop_', true);
        $proposal = [
            'id' => $id,
            'title' => $title,
            'author' => $author,
            'summary' => $summary,
            'details' => $details,
            'status' => 'submitted',
            'votes_for' => 0,
            'votes_against' => 0,
            'submitted_at' => date('c'),
        ];
        $this->proposals[$id] = $proposal;
        $this->save();
        return $proposal;
    }

    public function voteOnProposal(string $proposalId, string $voter, string $committeeType, bool $inFavor): void
    {
        if (!isset($this->proposals[$proposalId])) {
            throw new GovernanceException("Proposal not found");
        }

        $this->votes[] = [
            'proposal_id' => $proposalId,
            'voter' => $voter,
            'committee' => $committeeType,
            'in_favor' => $inFavor,
            'voted_at' => date('c'),
        ];

        if ($inFavor) {
            $this->proposals[$proposalId]['votes_for']++;
        } else {
            $this->proposals[$proposalId]['votes_against']++;
        }

        $totalVotes = $this->proposals[$proposalId]['votes_for'] + $this->proposals[$proposalId]['votes_against'];
        $approval = $totalVotes > 0
            ? $this->proposals[$proposalId]['votes_for'] / $totalVotes
            : 0;

        if ($totalVotes >= 3 && $approval >= 0.66) {
            $this->proposals[$proposalId]['status'] = 'approved';
        }

        $this->save();
    }

    public function getCommittees(): array
    {
        $result = [];
        foreach ($this->committees as $key => $c) {
            $result[$key] = [
                'name' => $c['name'],
                'responsibilities' => $c['responsibilities'],
                'member_count' => count($c['members']),
                'max_members' => $c['max_members'],
                'members' => $c['members'],
            ];
        }
        return $result;
    }

    public function getProposals(string $status = ''): array
    {
        if ($status) {
            return array_values(array_filter($this->proposals, fn($p) => $p['status'] === $status));
        }
        return array_values($this->proposals);
    }

    public function getVoteHistory(string $proposalId = ''): array
    {
        if ($proposalId) {
            return array_values(array_filter($this->votes, fn($v) => $v['proposal_id'] === $proposalId));
        }
        return $this->votes;
    }

    private function load(): void
    {
        $file = $this->dataDir . '/governance.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                if (isset($data['committees'])) {
                    foreach ($data['committees'] as $key => $c) {
                        if (isset($this->committees[$key])) {
                            $this->committees[$key]['members'] = $c['members'] ?? [];
                        }
                    }
                }
                $this->proposals = $data['proposals'] ?? [];
                $this->votes = $data['votes'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/governance.json',
            json_encode([
                'committees' => array_map(fn($c) => ['members' => $c['members']], $this->committees),
                'proposals' => $this->proposals,
                'votes' => $this->votes,
            ], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
