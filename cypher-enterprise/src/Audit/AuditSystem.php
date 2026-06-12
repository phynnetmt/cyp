<?php

namespace Cypher\Enterprise\Audit;

class AuditSystem
{
    private array $entries = [];
    private string $dataDir;
    private int $maxEntries;

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-enterprise/audit');
        $this->maxEntries = $config['max_entries'] ?? 100000;
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }
        $this->load();
    }

    public function record(string $action, string $actor, string $resource, array $context = [], string $status = 'success'): string
    {
        $id = uniqid('audit_', true);
        $entry = [
            'id' => $id,
            'action' => $action,
            'actor' => $actor,
            'resource' => $resource,
            'context' => $context,
            'status' => $status,
            'timestamp' => date('c'),
            'hash' => $this->computeHash($id, $action, $actor, $resource, $status),
        ];

        $this->entries[] = $entry;

        if (count($this->entries) > $this->maxEntries) {
            array_shift($this->entries);
        }

        $this->save();
        return $id;
    }

    public function query(array $filters = [], int $limit = 100): array
    {
        $results = $this->entries;

        if (!empty($filters['action'])) {
            $results = array_filter($results, fn($e) => $e['action'] === $filters['action']);
        }
        if (!empty($filters['actor'])) {
            $results = array_filter($results, fn($e) => $e['actor'] === $filters['actor']);
        }
        if (!empty($filters['resource'])) {
            $results = array_filter($results, fn($e) => $e['resource'] === $filters['resource']);
        }
        if (!empty($filters['status'])) {
            $results = array_filter($results, fn($e) => $e['status'] === $filters['status']);
        }
        if (!empty($filters['since'])) {
            $results = array_filter($results, fn($e) => $e['timestamp'] >= $filters['since']);
        }

        $results = array_reverse(array_values($results));
        return array_slice($results, 0, $limit);
    }

    public function getEntry(string $id): ?array
    {
        foreach ($this->entries as $entry) {
            if ($entry['id'] === $id) return $entry;
        }
        return null;
    }

    public function verifyIntegrity(): array
    {
        $verified = 0;
        $tampered = 0;

        foreach ($this->entries as $entry) {
            $expectedHash = $this->computeHash(
                $entry['id'], $entry['action'], $entry['actor'],
                $entry['resource'], $entry['status']
            );
            if ($entry['hash'] === $expectedHash) {
                $verified++;
            } else {
                $tampered++;
            }
        }

        return ['verified' => $verified, 'tampered' => $tampered, 'total' => count($this->entries)];
    }

    public function getStats(): array
    {
        $actionCounts = [];
        foreach ($this->entries as $entry) {
            $action = $entry['action'];
            $actionCounts[$action] = ($actionCounts[$action] ?? 0) + 1;
        }

        return [
            'total_entries' => count($this->entries),
            'unique_actions' => count($actionCounts),
            'action_breakdown' => $actionCounts,
        ];
    }

    public function export(string $format = 'json'): string
    {
        return match ($format) {
            'json' => json_encode($this->entries, JSON_PRETTY_PRINT),
            'csv' => $this->exportCSV(),
            default => json_encode($this->entries),
        };
    }

    private function computeHash(string $id, string $action, string $actor, string $resource, string $status): string
    {
        $data = "{$id}|{$action}|{$actor}|{$resource}|{$status}";
        return hash('sha256', $data);
    }

    private function exportCSV(): string
    {
        $csv = "id,action,actor,resource,status,timestamp\n";
        foreach ($this->entries as $e) {
            $csv .= "{$e['id']},{$e['action']},{$e['actor']},{$e['resource']},{$e['status']},{$e['timestamp']}\n";
        }
        return $csv;
    }

    private function load(): void
    {
        $file = $this->dataDir . '/audit.log';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if (is_array($data)) $this->entries = $data;
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/audit.log',
            json_encode($this->entries, JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
