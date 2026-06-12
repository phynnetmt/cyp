<?php

namespace Cypher\Ecosystem\Content;

class ContentEcosystem
{
    private array $documentation = [];
    private array $tutorials = [];
    private array $blueprints = [];
    private array $sampleApps = [];
    private string $dataDir;

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-ecosystem/content');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }
        $this->load();
    }

    public function publishDocumentation(string $title, string $content, string $category, string $version): array
    {
        $id = uniqid('doc_', true);
        $doc = [
            'id' => $id,
            'title' => $title,
            'content' => $content,
            'category' => $category,
            'version' => $version,
            'status' => 'published',
            'views' => 0,
            'updated_at' => date('c'),
            'created_at' => date('c'),
        ];
        $this->documentation[$id] = $doc;
        $this->save();
        return $doc;
    }

    public function searchDocumentation(string $query, string $category = ''): array
    {
        $results = array_filter($this->documentation, function($doc) use ($query, $category) {
            if ($category && $doc['category'] !== $category) return false;
            return str_contains(strtolower($doc['title']), strtolower($query))
                || str_contains(strtolower($doc['content']), strtolower($query));
        });
        return array_values($results);
    }

    public function createTutorial(string $title, string $description, string $level, int $durationMinutes, array $steps): array
    {
        $id = uniqid('tut_', true);
        $tutorial = [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'level' => $level,
            'duration_minutes' => $durationMinutes,
            'steps' => $steps,
            'status' => 'published',
            'completions' => 0,
            'created_at' => date('c'),
        ];
        $this->tutorials[$id] = $tutorial;
        $this->save();
        return $tutorial;
    }

    public function completeTutorial(string $tutorialId): void
    {
        if (isset($this->tutorials[$tutorialId])) {
            $this->tutorials[$tutorialId]['completions']++;
            $this->save();
        }
    }

    public function createBlueprint(string $title, string $description, string $category, array $architecture): array
    {
        $id = uniqid('bp_', true);
        $blueprint = [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'category' => $category,
            'architecture' => $architecture,
            'status' => 'published',
            'created_at' => date('c'),
        ];
        $this->blueprints[$id] = $blueprint;
        $this->save();
        return $blueprint;
    }

    public function createSampleApp(string $name, string $description, string $category, array $files): array
    {
        $id = uniqid('app_', true);
        $app = [
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'category' => $category,
            'files' => $files,
            'downloads' => 0,
            'status' => 'published',
            'created_at' => date('c'),
        ];
        $this->sampleApps[$id] = $app;
        $this->save();
        return $app;
    }

    public function recordDownload(string $appId): void
    {
        if (isset($this->sampleApps[$appId])) {
            $this->sampleApps[$appId]['downloads']++;
            $this->save();
        }
    }

    public function listTutorials(string $level = ''): array
    {
        if ($level) {
            return array_values(array_filter($this->tutorials, fn($t) => $t['level'] === $level));
        }
        return array_values($this->tutorials);
    }

    public function listBlueprints(string $category = ''): array
    {
        if ($category) {
            return array_values(array_filter($this->blueprints, fn($b) => $b['category'] === $category));
        }
        return array_values($this->blueprints);
    }

    public function listSampleApps(string $category = ''): array
    {
        if ($category) {
            return array_values(array_filter($this->sampleApps, fn($a) => $a['category'] === $category));
        }
        return array_values($this->sampleApps);
    }

    public function getStats(): array
    {
        return [
            'documents' => count($this->documentation),
            'tutorials' => count($this->tutorials),
            'blueprints' => count($this->blueprints),
            'sample_apps' => count($this->sampleApps),
            'total_downloads' => array_sum(array_column($this->sampleApps, 'downloads')),
        ];
    }

    private function load(): void
    {
        $file = $this->dataDir . '/content.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->documentation = $data['documentation'] ?? [];
                $this->tutorials = $data['tutorials'] ?? [];
                $this->blueprints = $data['blueprints'] ?? [];
                $this->sampleApps = $data['samples'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/content.json',
            json_encode([
                'documentation' => $this->documentation,
                'tutorials' => $this->tutorials,
                'blueprints' => $this->blueprints,
                'samples' => $this->sampleApps,
            ], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
