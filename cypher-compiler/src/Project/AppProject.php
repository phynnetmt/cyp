<?php

namespace Cypher\Compiler\Project;

class AppProject
{
    public const STRUCTURE = [
        'app.cyp' => 'Application entry point (optional — conventions used if absent)',
        'pages/' => 'Frontend page definitions (*.cyp)',
        'models/' => 'Database model definitions (*.cyp)',
        'agents/' => 'AI agent definitions (*.cyp)',
        'workflows/' => 'Business workflow definitions (*.cyp)',
        'components/' => 'Reusable UI component definitions (*.cyp)',
        'api/' => 'API endpoint definitions (*.cyp)',
        'services/' => 'Service/business logic definitions (*.cyp)',
    ];

    private string $rootPath;
    private ProjectConfig $config;
    private array $cypFiles = [];
    private array $manifest = [];

    public function __construct(string $rootPath)
    {
        $this->rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
        $this->config = new ProjectConfig($this->rootPath);
    }

    public static function create(string $path, string $name = 'my-app'): self
    {
        $root = rtrim($path, DIRECTORY_SEPARATOR);

        foreach (array_keys(self::STRUCTURE) as $entry) {
            $fullPath = $root . DIRECTORY_SEPARATOR . $entry;
            if (str_ends_with($entry, '/')) {
                if (!is_dir($fullPath)) {
                    mkdir($fullPath, 0777, true);
                }
            }
        }

        ProjectConfig::init($root, $name);

        return new self($root);
    }

    public function discover(): array
    {
        $this->cypFiles = [];
        $this->manifest = [];

        $patterns = [
            $this->rootPath . DIRECTORY_SEPARATOR . '*.cyp',
            $this->rootPath . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . '*.cyp',
            $this->rootPath . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . '*.cyp',
            $this->rootPath . DIRECTORY_SEPARATOR . 'agents' . DIRECTORY_SEPARATOR . '*.cyp',
            $this->rootPath . DIRECTORY_SEPARATOR . 'workflows' . DIRECTORY_SEPARATOR . '*.cyp',
            $this->rootPath . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . '*.cyp',
            $this->rootPath . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . '*.cyp',
            $this->rootPath . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . '*.cyp',
        ];

        foreach ($patterns as $pattern) {
            foreach (glob($pattern) as $file) {
                $relativePath = str_replace($this->rootPath . DIRECTORY_SEPARATOR, '', $file);
                $category = $this->categorize($relativePath);
                $this->cypFiles[] = $file;
                $this->manifest[] = [
                    'path' => $relativePath,
                    'absolute' => $file,
                    'category' => $category,
                    'name' => pathinfo($file, PATHINFO_FILENAME),
                ];
            }
        }

        return $this->manifest;
    }

    public function getRootPath(): string
    {
        return $this->rootPath;
    }

    public function getConfig(): ProjectConfig
    {
        return $this->config;
    }

    public function getCypFiles(): array
    {
        if (empty($this->manifest)) {
            $this->discover();
        }
        return $this->cypFiles;
    }

    public function getManifest(): array
    {
        if (empty($this->manifest)) {
            $this->discover();
        }
        return $this->manifest;
    }

    public function getSourceCodeMap(): array
    {
        if (empty($this->manifest)) {
            $this->discover();
        }
        $map = [];
        foreach ($this->cypFiles as $file) {
            $relativePath = str_replace($this->rootPath . DIRECTORY_SEPARATOR, '', $file);
            $map[$relativePath] = file_get_contents($file);
        }
        return $map;
    }

    public function hasFile(string $relativePath): bool
    {
        return file_exists($this->rootPath . DIRECTORY_SEPARATOR . $relativePath);
    }

    private function categorize(string $relativePath): string
    {
        $dir = dirname($relativePath);
        if ($dir === '.') return 'root';
        return $dir;
    }
}
