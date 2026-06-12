<?php

namespace Cypher\Compiler\Registry;

class RegistryClient
{
    private string $registryUrl;
    private ?string $authToken = null;
    private string $cacheDir;

    public function __construct(string $registryUrl = 'https://registry.cyphercode.ai')
    {
        $this->registryUrl = rtrim($registryUrl, '/');
        $this->cacheDir = getcwd() . '/.cyp-cache';
    }

    public function fetchPackage(string $name, ?string $version = null): ?array
    {
        $cacheKey = "pkg_{$name}_" . ($version ?? 'latest');
        $cacheFile = $this->cacheDir . '/' . md5($cacheKey) . '.json';

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 300) {
            return json_decode(file_get_contents($cacheFile), true);
        }

        try {
            $url = "{$this->registryUrl}/api/packages/{$name}";
            if ($version) {
                $url .= "/{$version}";
            }

            $context = $this->createContext();
            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                return $this->getOfflineFallback($name, $version);
            }

            $data = json_decode($response, true);
            if ($data) {
                if (!is_dir($this->cacheDir)) {
                    mkdir($this->cacheDir, 0777, true);
                }
                file_put_contents($cacheFile, json_encode($data));
            }

            return $data ?: null;
        } catch (\Exception $e) {
            return $this->getOfflineFallback($name, $version);
        }
    }

    public function search(string $query, int $limit = 20): array
    {
        try {
            $url = "{$this->registryUrl}/api/search?q=" . urlencode($query) . "&limit={$limit}";
            $context = $this->createContext();
            $response = @file_get_contents($url, false, $context);
            if ($response === false) return [];
            return json_decode($response, true) ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function publish(string $packagePath, array $metadata): array
    {
        if (!$this->authToken) {
            return ['success' => false, 'error' => 'Not authenticated. Run cyp login first.'];
        }

        $payload = json_encode($metadata);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    "Authorization: Bearer {$this->authToken}",
                    'Content-Length: ' . strlen($payload),
                ]),
                'content' => $payload,
                'timeout' => 30,
            ],
        ]);

        try {
            $url = "{$this->registryUrl}/api/packages";
            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                return ['success' => false, 'error' => 'Failed to publish package'];
            }
            return json_decode($response, true) ?? ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function authenticate(string $username, string $password): ?string
    {
        $payload = json_encode(['username' => $username, 'password' => $password]);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nContent-Length: " . strlen($payload),
                'content' => $payload,
                'timeout' => 15,
            ],
        ]);

        try {
            $url = "{$this->registryUrl}/api/auth/login";
            $response = @file_get_contents($url, false, $context);
            if ($response === false) return null;

            $data = json_decode($response, true);
            $token = $data['token'] ?? null;

            if ($token) {
                $this->authToken = $token;
                $this->storeToken($token);
            }

            return $token;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function setToken(?string $token): void
    {
        $this->authToken = $token;
    }

    public function getToken(): ?string
    {
        if ($this->authToken) return $this->authToken;
        return $this->loadStoredToken();
    }

    public function isAuthenticated(): bool
    {
        return $this->getToken() !== null;
    }

    public function logout(): void
    {
        $this->authToken = null;
        $tokenFile = $this->getTokenFile();
        if (file_exists($tokenFile)) {
            unlink($tokenFile);
        }
    }

    public function getRegistryUrl(): string
    {
        return $this->registryUrl;
    }

    private function storeToken(string $token): void
    {
        $tokenFile = $this->getTokenFile();
        $dir = dirname($tokenFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        file_put_contents($tokenFile, $token, LOCK_EX);
    }

    private function loadStoredToken(): ?string
    {
        $tokenFile = $this->getTokenFile();
        if (file_exists($tokenFile)) {
            return trim(file_get_contents($tokenFile));
        }
        return null;
    }

    private function getTokenFile(): string
    {
        return getenv('HOME') ? getenv('HOME') . '/.cyp/credentials.json' : getcwd() . '/.cyp-credentials';
    }

    private function createContext()
    {
        $headers = ['User-Agent: CypherCLI/0.4.0'];
        $token = $this->getToken();
        if ($token) {
            $headers[] = "Authorization: Bearer {$token}";
        }
        return stream_context_create([
            'http' => ['header' => implode("\r\n", $headers), 'timeout' => 10],
        ]);
    }

    private function getOfflineFallback(string $name, ?string $version): ?array
    {
        $knownPackages = [
            'cyp/std' => ['1.0.0', 'CYP Standard Library'],
            'cyp/string' => ['1.0.0', 'String utilities'],
            'cyp/http' => ['1.0.0', 'HTTP client/server'],
            'cyp/json' => ['1.0.0', 'JSON utilities'],
            'cyp/auth' => ['1.0.0', 'Authentication'],
            'cyp/postgres' => ['1.0.0', 'PostgreSQL driver'],
            'cyp/redis' => ['1.0.0', 'Redis client'],
            'cyp/ui' => ['1.0.0', 'UI components'],
            'cyp/ai' => ['1.0.0', 'AI integration'],
            'cyp/storage' => ['1.0.0', 'File storage'],
        ];

        if (isset($knownPackages[$name])) {
            $ver = $version ?? $knownPackages[$name][0];
            return [
                'name' => $name,
                'version' => $ver,
                'description' => $knownPackages[$name][1],
                'versions' => [$ver => ['dependencies' => []]],
            ];
        }

        return null;
    }
}
