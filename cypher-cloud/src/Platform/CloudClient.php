<?php

namespace Cypher\Cloud\Platform;

class CloudClient
{
    private string $apiUrl;
    private ?string $apiKey = null;
    private string $dataDir;

    public function __construct(string $apiUrl = 'https://api.cyphercode.ai')
    {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->dataDir = getenv('HOME')
            ? getenv('HOME') . '/.cyp-cloud'
            : getcwd() . '/.cyp-cloud';
    }

    public function authenticate(string $apiKey): void
    {
        $this->apiKey = $apiKey;
        $this->storeApiKey($apiKey);
    }

    public function isAuthenticated(): bool
    {
        return $this->getApiKey() !== null;
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    public function request(string $method, string $path, array $data = []): array
    {
        $key = $this->getApiKey();
        $headers = ['Content-Type: application/json', 'User-Agent: CypherCloudCLI/0.6.0'];
        if ($key) {
            $headers[] = "Authorization: Bearer {$key}";
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $data ? json_encode($data) : null,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);

        $url = $this->apiUrl . $path;
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new CloudException("Failed to connect to Cypher Cloud at {$url}");
        }

        $decoded = json_decode($response, true);
        if ($decoded === null && $response !== '') {
            throw new CloudException("Invalid response from Cypher Cloud");
        }

        return $decoded ?? [];
    }

    public function deployProject(string $projectName, array $options = []): array
    {
        return $this->request('POST', '/api/deployments', [
            'project' => $projectName,
            'options' => $options,
        ]);
    }

    public function getDeployment(string $id): array
    {
        return $this->request('GET', "/api/deployments/{$id}");
    }

    public function listDeployments(string $project = ''): array
    {
        $path = '/api/deployments';
        if ($project) $path .= '?project=' . urlencode($project);
        return $this->request('GET', $path);
    }

    public function deleteDeployment(string $id): array
    {
        return $this->request('DELETE', "/api/deployments/{$id}");
    }

    public function createDatabase(string $name, string $type = 'postgresql', array $options = []): array
    {
        return $this->request('POST', '/api/databases', [
            'name' => $name,
            'type' => $type,
            'options' => $options,
        ]);
    }

    public function listDatabases(): array
    {
        return $this->request('GET', '/api/databases');
    }

    public function createSecret(string $name, string $value): array
    {
        return $this->request('POST', '/api/secrets', [
            'name' => $name,
            'value' => base64_encode($value),
        ]);
    }

    public function listSecrets(): array
    {
        return $this->request('GET', '/api/secrets');
    }

    public function deleteSecret(string $name): array
    {
        return $this->request('DELETE', "/api/secrets/{$name}");
    }

    public function getUsage(): array
    {
        return $this->request('GET', '/api/usage');
    }

    private function getApiKey(): ?string
    {
        if ($this->apiKey) return $this->apiKey;
        $file = $this->dataDir . '/credentials.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            return $data['api_key'] ?? null;
        }
        return null;
    }

    private function storeApiKey(string $key): void
    {
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }
        file_put_contents(
            $this->dataDir . '/credentials.json',
            json_encode(['api_key' => $key, 'stored_at' => date('c')], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
