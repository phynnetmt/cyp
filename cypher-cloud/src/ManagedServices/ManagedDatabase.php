<?php

namespace Cypher\Cloud\ManagedServices;

class ManagedDatabase
{
    private array $databases = [];
    private string $dataDir;

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-cloud/databases');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }
        $this->load();
    }

    public function create(string $name, string $type = 'postgresql', array $options = []): array
    {
        $id = uniqid('db_', true);
        $db = [
            'id' => $id,
            'name' => $name,
            'type' => $type,
            'status' => 'creating',
            'options' => $options,
            'connection' => $this->generateConnectionString($name, $type),
            'created_at' => date('c'),
        ];

        $this->databases[$id] = $db;
        $db['status'] = 'active';
        $this->save();

        return $db;
    }

    public function get(string $id): ?array
    {
        return $this->databases[$id] ?? null;
    }

    public function list(): array
    {
        return array_values($this->databases);
    }

    public function delete(string $id): void
    {
        unset($this->databases[$id]);
        $this->save();
    }

    public function getStats(): array
    {
        return [
            'total' => count($this->databases),
            'by_type' => array_count_values(array_column($this->databases, 'type')),
        ];
    }

    private function generateConnectionString(string $name, string $type): string
    {
        return match ($type) {
            'postgresql' => "postgresql://cyp:secret@localhost:5432/{$name}",
            'redis' => "redis://localhost:6379/0",
            'mysql' => "mysql://cyp:secret@localhost:3306/{$name}",
            default => "{$type}://localhost/{$name}",
        };
    }

    private function load(): void
    {
        $file = $this->dataDir . '/databases.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) $this->databases = $data;
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/databases.json',
            json_encode($this->databases, JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
