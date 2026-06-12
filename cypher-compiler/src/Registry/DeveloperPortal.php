<?php

namespace Cypher\Compiler\Registry;

class DeveloperPortal
{
    private RegistryClient $client;
    private string $portalUrl;

    public function __construct(string $portalUrl = 'https://hub.cyphercode.ai')
    {
        $this->portalUrl = rtrim($portalUrl, '/');
        $this->client = new RegistryClient();
    }

    public function getPackageUrl(string $name): string
    {
        return "{$this->portalUrl}/packages/{$name}";
    }

    public function getProfileUrl(string $username): string
    {
        return "{$this->portalUrl}/@" . urlencode($username);
    }

    public function getDocumentationUrl(string $name, ?string $version = null): string
    {
        $url = "{$this->portalUrl}/docs/{$name}";
        if ($version) $url .= "/{$version}";
        return $url;
    }

    public function getApiReferenceUrl(string $name): string
    {
        return "{$this->portalUrl}/api/{$name}";
    }

    public function generatePackageReadme(array $metadata): string
    {
        $name = $metadata['name'] ?? 'unknown';
        $desc = $metadata['description'] ?? '';
        $version = $metadata['version'] ?? '0.1.0';

        return <<<MD
# {$name}

{$desc}

## Installation

```bash
cyp install {$name}
```

## Version

{$version}

## License

{$metadata['license'] ?? 'CYP-1.0'}

## Author

{$metadata['author'] ?? 'Unknown'}
MD;
    }

    public function searchPackages(string $query): array
    {
        return $this->client->search($query);
    }

    public function getPortalUrl(): string
    {
        return $this->portalUrl;
    }
}
