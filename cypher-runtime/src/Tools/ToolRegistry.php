<?php

namespace Cypher\Runtime\Tools;

use Cypher\Runtime\Memory\MemoryManager;

class ToolRegistry
{
    private array $tools = [];
    private ?MemoryManager $memory = null;

    public function __construct(?MemoryManager $memory = null)
    {
        $this->memory = $memory;
        $this->registerBuiltin();
    }

    public function register(string $name, callable $handler, array $schema = []): void
    {
        $this->tools[$name] = [
            'handler' => $handler,
            'schema' => $schema,
        ];
    }

    public function execute(string $name, array $arguments = []): mixed
    {
        $tool = $this->tools[$name] ?? null;
        if (!$tool) {
            throw new ToolException("Tool '{$name}' not found");
        }

        try {
            return ($tool['handler'])($arguments);
        } catch (\Exception $e) {
            throw new ToolException("Tool '{$name}' execution failed: {$e->getMessage()}");
        }
    }

    public function getTool(string $name): ?array
    {
        return $this->tools[$name] ?? null;
    }

    public function listTools(): array
    {
        $result = [];
        foreach ($this->tools as $name => $tool) {
            $result[$name] = $tool['schema'];
        }
        return $result;
    }

    public function hasTool(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function getToolCount(): int
    {
        return count($this->tools);
    }

    public function setMemoryManager(MemoryManager $memory): void
    {
        $this->memory = $memory;
    }

    private function registerBuiltin(): void
    {
        // Safe calculator using bcmath or basic arithmetic parsing
        $this->register('calculator', function (array $args) {
            $expr = $args['expression'] ?? '';
            $expr = preg_replace('/[^0-9+\-*.\/()% ]/', '', $expr);
            $expr = trim($expr);
            if ($expr === '') {
                return ['error' => 'Empty expression'];
            }
            if (preg_match('/^[\d+\-*.\/()%.\s]+$/', $expr)) {
                $result = @eval("return {$expr};");
                if ($result === false) {
                    return ['error' => 'Invalid expression'];
                }
                return ['result' => $result];
            }
            return ['error' => 'Expression contains invalid characters'];
        }, [
            'description' => 'Evaluate a mathematical expression',
            'parameters' => ['expression' => 'string'],
        ]);

        $this->register('datetime', function (array $args) {
            $format = $args['format'] ?? 'Y-m-d H:i:s';
            try {
                $tz = $args['timezone'] ?? 'UTC';
                $dt = new \DateTime('now', new \DateTimeZone($tz));
                return ['datetime' => $dt->format($format)];
            } catch (\Exception) {
                return ['datetime' => date($format)];
            }
        }, [
            'description' => 'Get current date and time',
            'parameters' => ['format' => 'string', 'timezone' => 'string'],
        ]);

        $this->register('search', function (array $args) {
            $query = $args['query'] ?? '';
            return ['results' => ["Simulated search result for: {$query}"], 'count' => 1];
        }, [
            'description' => 'Search for information',
            'parameters' => ['query' => 'string'],
        ]);

        $this->register('memory_store', function (array $args) {
            if ($this->memory) {
                $this->memory->store([
                    'content' => $args['value'] ?? '',
                    'type' => $args['type'] ?? 'tool',
                    'metadata' => ['key' => $args['key'] ?? uniqid()],
                ]);
                return ['stored' => true, 'key' => $args['key'] ?? uniqid()];
            }
            return ['stored' => false, 'error' => 'Memory not available'];
        }, [
            'description' => 'Store data in agent memory',
            'parameters' => ['key' => 'string', 'value' => 'mixed', 'type' => 'string'],
        ]);

        $this->register('memory_retrieve', function (array $args) {
            if ($this->memory) {
                $key = $args['key'] ?? '';
                $data = $this->memory->recall($key, 'long_term');
                if ($data) {
                    return ['data' => $data, 'key' => $key];
                }
                $results = $this->memory->search($args['query'] ?? $key, 1);
                return ['data' => $results[0] ?? null, 'key' => $key];
            }
            return ['data' => null, 'error' => 'Memory not available'];
        }, [
            'description' => 'Retrieve data from agent memory',
            'parameters' => ['key' => 'string', 'query' => 'string'],
        ]);

        $this->register('http_get', function (array $args) {
            $url = $args['url'] ?? '';
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return ['status' => 400, 'body' => 'Invalid URL'];
            }
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'user_agent' => 'CypherAgent/1.0',
                    'max_redirects' => 3,
                ],
            ]);
            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                return ['status' => 500, 'body' => 'Error fetching URL'];
            }
            $length = strlen($response);
            if ($length > 10485760) {
                return ['status' => 413, 'body' => 'Response too large (>10MB)'];
            }
            return ['status' => 200, 'body' => mb_substr($response, 0, 10485760)];
        }, [
            'description' => 'Make HTTP GET request',
            'parameters' => ['url' => 'string'],
        ]);
    }
}
