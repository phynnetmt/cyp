<?php

namespace Cypher\Cloud\Platform;

class LocalDevServer
{
    private array $config;
    private array $services = [];
    private bool $running = false;
    private int $port;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->port = $config['port'] ?? 8080;
    }

    public function start(): void
    {
        if ($this->running) return;
        $this->running = true;

        $this->services = [
            'api' => true,
            'deployments' => [],
            'databases' => [],
            'secrets' => [],
        ];

        echo "Cypher Cloud Local Dev Server running on http://localhost:{$this->port}\n";
    }

    public function stop(): void
    {
        $this->running = false;
        echo "Cypher Cloud Local Dev Server stopped.\n";
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getServiceStatus(): array
    {
        return [
            'running' => $this->running,
            'port' => $this->port,
            'services' => $this->services,
            'uptime' => $this->running ? time() : 0,
        ];
    }

    public function simulateRequest(string $method, string $path, array $data = []): array
    {
        return match (true) {
            $path === '/health' => ['status' => 'ok', 'version' => '0.6.0'],
            $path === '/api/deployments' && $method === 'GET' => $this->services['deployments'],
            str_starts_with($path, '/api/deployments/') && $method === 'GET' => $this->services['deployments'][basename($path)] ?? ['error' => 'Not found'],
            $path === '/api/databases' && $method === 'GET' => $this->services['databases'],
            $path === '/api/secrets' && $method === 'GET' => $this->services['secrets'],
            $path === '/api/usage' => [
                'deployments' => count($this->services['deployments']),
                'databases' => count($this->services['databases']),
                'bandwidth_gb' => 0,
                'compute_hours' => 0,
            ],
            default => ['error' => 'Not implemented', 'path' => $path],
        };
    }
}
