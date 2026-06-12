<?php

namespace Cypher\Enterprise\EnterpriseDev;

class EnterpriseDevPlatform
{
    private array $privateRegistries = [];
    private array $privateTemplates = [];
    private array $componentLibraries = [];
    private string $dataDir;

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-enterprise/dev');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }
        $this->load();
    }

    public function createPrivateRegistry(string $name, string $orgId, array $options = []): array
    {
        $id = uniqid('reg_', true);
        $registry = [
            'id' => $id,
            'name' => $name,
            'org_id' => $orgId,
            'options' => $options,
            'package_count' => 0,
            'status' => 'active',
            'created_at' => date('c'),
        ];
        $this->privateRegistries[$id] = $registry;
        $this->save();
        return $registry;
    }

    public function publishToRegistry(string $registryId, string $packageName, string $version, array $metadata = []): string
    {
        if (!isset($this->privateRegistries[$registryId])) {
            throw new EnterpriseDevException("Private registry not found");
        }

        $id = uniqid('pkg_', true);
        $this->privateRegistries[$registryId]['package_count']++;
        $this->save();

        return $id;
    }

    public function createTemplate(string $name, string $type, string $orgId, array $content = []): array
    {
        $id = uniqid('tpl_', true);
        $template = [
            'id' => $id,
            'name' => $name,
            'type' => $type,
            'org_id' => $orgId,
            'content' => $content,
            'status' => 'active',
            'created_at' => date('c'),
        ];
        $this->privateTemplates[$id] = $template;
        $this->save();
        return $template;
    }

    public function createComponentLibrary(string $name, string $orgId, array $components = []): array
    {
        $id = uniqid('lib_', true);
        $library = [
            'id' => $id,
            'name' => $name,
            'org_id' => $orgId,
            'components' => $components,
            'status' => 'active',
            'created_at' => date('c'),
        ];
        $this->componentLibraries[$id] = $library;
        $this->save();
        return $library;
    }

    public function listPrivateRegistries(string $orgId = ''): array
    {
        if ($orgId) {
            return array_values(array_filter($this->privateRegistries, fn($r) => $r['org_id'] === $orgId));
        }
        return array_values($this->privateRegistries);
    }

    public function listTemplates(string $orgId = '', string $type = ''): array
    {
        $results = $orgId
            ? array_filter($this->privateTemplates, fn($t) => $t['org_id'] === $orgId)
            : $this->privateTemplates;

        if ($type) {
            $results = array_filter($results, fn($t) => $t['type'] === $type);
        }

        return array_values($results);
    }

    public function listComponentLibraries(string $orgId = ''): array
    {
        if ($orgId) {
            return array_values(array_filter($this->componentLibraries, fn($l) => $l['org_id'] === $orgId));
        }
        return array_values($this->componentLibraries);
    }

    private function load(): void
    {
        $file = $this->dataDir . '/enterprise_dev.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->privateRegistries = $data['registries'] ?? [];
                $this->privateTemplates = $data['templates'] ?? [];
                $this->componentLibraries = $data['libraries'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/enterprise_dev.json',
            json_encode([
                'registries' => $this->privateRegistries,
                'templates' => $this->privateTemplates,
                'libraries' => $this->componentLibraries,
            ], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
