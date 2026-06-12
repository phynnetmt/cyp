<?php

namespace Cypher\Compiler\Project;

class ProjectConfig
{
    private array $data;
    private string $path;

    public function __construct(string $projectRoot)
    {
        $this->path = $projectRoot . DIRECTORY_SEPARATOR . 'cypher.json';
        if (file_exists($this->path)) {
            $content = file_get_contents($this->path);
            $this->data = json_decode($content, true) ?? $this->defaults();
        } else {
            $this->data = $this->defaults();
        }
    }

    public static function init(string $root, string $name): void
    {
        $config = [
            'name' => $name,
            'version' => '0.1.0',
            'compiler' => 'cypc',
            'source' => '.',
            'build' => [
                'output' => 'build',
                'clean' => true,
            ],
            'targets' => [
                'backend' => ['language' => 'PHP', 'framework' => 'Laravel 12'],
                'frontend' => ['framework' => 'React', 'language' => 'TypeScript', 'styling' => 'Tailwind CSS'],
                'database' => ['engine' => 'PostgreSQL', 'extensions' => ['PGVector']],
            ],
            'language' => [
                'extension' => '.cyp',
                'version' => '0.1.0',
            ],
        ];

        file_put_contents(
            $root . DIRECTORY_SEPARATOR . 'cypher.json',
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->data;
        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }
        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $ref = &$this->data;
        foreach ($keys as $k) {
            if (!isset($ref[$k]) || !is_array($ref[$k])) {
                $ref[$k] = [];
            }
            $ref = &$ref[$k];
        }
        $ref = $value;
    }

    public function save(): void
    {
        file_put_contents(
            $this->path,
            json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    public function toArray(): array
    {
        return $this->data;
    }

    private function defaults(): array
    {
        return [
            'name' => 'cyp-project',
            'version' => '0.1.0',
            'compiler' => 'cypc',
            'source' => '.',
            'build' => ['output' => 'build', 'clean' => true],
            'targets' => [
                'backend' => ['language' => 'PHP', 'framework' => 'Laravel 12'],
                'frontend' => ['framework' => 'React', 'language' => 'TypeScript', 'styling' => 'Tailwind CSS'],
                'database' => ['engine' => 'PostgreSQL', 'extensions' => ['PGVector']],
            ],
        ];
    }
}
