<?php

namespace Cypher\Ecosystem\Governance;

class OpenSourceGovernance
{
    private array $rfcs = [];
    private array $proposals = [];
    private array $contributors = [];
    private string $dataDir;

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-ecosystem/governance');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }
        $this->load();
    }

    public function submitRFC(string $title, string $author, string $summary, string $details, string $type = 'feature'): array
    {
        $id = uniqid('rfc_', true);
        $rfc = [
            'id' => $id,
            'title' => $title,
            'author' => $author,
            'summary' => $summary,
            'details' => $details,
            'type' => $type,
            'status' => 'draft',
            'votes_for' => 0,
            'votes_against' => 0,
            'comments' => [],
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];
        $this->rfcs[$id] = $rfc;
        $this->save();
        return $rfc;
    }

    public function voteOnRFC(string $rfcId, string $voter, bool $inFavor): void
    {
        if (!isset($this->rfcs[$rfcId])) {
            throw new GovernanceException("RFC not found");
        }

        if ($inFavor) {
            $this->rfcs[$rfcId]['votes_for']++;
        } else {
            $this->rfcs[$rfcId]['votes_against']++;
        }

        $totalVotes = $this->rfcs[$rfcId]['votes_for'] + $this->rfcs[$rfcId]['votes_against'];
        $approval = $totalVotes > 0 ? $this->rfcs[$rfcId]['votes_for'] / $totalVotes : 0;

        if ($totalVotes >= 5 && $approval >= 0.75) {
            $this->rfcs[$rfcId]['status'] = 'accepted';
        } elseif ($totalVotes >= 5 && $approval < 0.5) {
            $this->rfcs[$rfcId]['status'] = 'rejected';
        }

        $this->save();
    }

    public function addComment(string $rfcId, string $author, string $comment): void
    {
        if (isset($this->rfcs[$rfcId])) {
            $this->rfcs[$rfcId]['comments'][] = [
                'author' => $author,
                'comment' => $comment,
                'created_at' => date('c'),
            ];
            $this->rfcs[$rfcId]['updated_at'] = date('c');
            $this->save();
        }
    }

    public function submitProposal(string $title, string $author, string $content, string $category): array
    {
        $id = uniqid('prop_', true);
        $proposal = [
            'id' => $id,
            'title' => $title,
            'author' => $author,
            'content' => $content,
            'category' => $category,
            'status' => 'under_review',
            'votes' => 0,
            'submitted_at' => date('c'),
        ];
        $this->proposals[$id] = $proposal;
        $this->save();
        return $proposal;
    }

    public function voteOnProposal(string $proposalId, string $voter): void
    {
        if (isset($this->proposals[$proposalId])) {
            $this->proposals[$proposalId]['votes']++;
            if ($this->proposals[$proposalId]['votes'] >= 10) {
                $this->proposals[$proposalId]['status'] = 'approved';
            }
            $this->save();
        }
    }

    public function registerContributor(string $githubUsername, string $areas, string $contributions = ''): string
    {
        $id = uniqid('contr_', true);
        $this->contributors[$id] = [
            'id' => $id,
            'github_username' => $githubUsername,
            'areas' => $areas,
            'contributions' => $contributions,
            'status' => 'active',
            'prs_merged' => 0,
            'issues_closed' => 0,
            'joined_at' => date('c'),
        ];
        $this->save();
        return $id;
    }

    public function recordContribution(string $contributorId, string $type): void
    {
        if (isset($this->contributors[$contributorId])) {
            if ($type === 'pr') $this->contributors[$contributorId]['prs_merged']++;
            if ($type === 'issue') $this->contributors[$contributorId]['issues_closed']++;
            $this->save();
        }
    }

    public function listRFCs(string $status = ''): array
    {
        if ($status) {
            return array_values(array_filter($this->rfcs, fn($r) => $r['status'] === $status));
        }
        return array_values($this->rfcs);
    }

    public function listProposals(string $status = ''): array
    {
        if ($status) {
            return array_values(array_filter($this->proposals, fn($p) => $p['status'] === $status));
        }
        return array_values($this->proposals);
    }

    public function topContributors(int $limit = 10): array
    {
        $sorted = $this->contributors;
        usort($sorted, fn($a, $b) => ($b['prs_merged'] + $b['issues_closed']) <=> ($a['prs_merged'] + $a['issues_closed']));
        return array_slice($sorted, 0, $limit);
    }

    public function getStats(): array
    {
        return [
            'rfcs' => count($this->rfcs),
            'proposals' => count($this->proposals),
            'contributors' => count($this->contributors),
            'total_prs' => array_sum(array_column($this->contributors, 'prs_merged')),
            'total_issues' => array_sum(array_column($this->contributors, 'issues_closed')),
        ];
    }

    public function getRoadmap(): array
    {
        $acceptedRfcs = array_filter($this->rfcs, fn($r) => $r['status'] === 'accepted');
        $roadmap = [];

        foreach ($acceptedRfcs as $rfc) {
            $quarter = 'Q' . ceil(rand(1, 4));
            $roadmap[$quarter][] = [
                'title' => $rfc['title'],
                'summary' => $rfc['summary'],
                'rfc_id' => $rfc['id'],
            ];
        }

        ksort($roadmap);
        return $roadmap;
    }

    private function load(): void
    {
        $file = $this->dataDir . '/governance.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->rfcs = $data['rfcs'] ?? [];
                $this->proposals = $data['proposals'] ?? [];
                $this->contributors = $data['contributors'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/governance.json',
            json_encode([
                'rfcs' => $this->rfcs,
                'proposals' => $this->proposals,
                'contributors' => $this->contributors,
            ], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
