<?php

namespace Cypher\Compiler\PackageManager;

class PackageJson
{
    public string $name = '';
    public string $version = '0.1.0';
    public string $description = '';
    public string $author = '';
    public string $license = 'CYP-1.0';
    public array $dependencies = [];
    public array $devDependencies = [];
    public array $scripts = [];
    public array $extra = [];
    public string $path = '';

    private const REQUIRED_FIELDS = ['name', 'version', 'description', 'license'];

    public static function load(string $path): self
    {
        if (!file_exists($path)) {
            throw new PackageManagerException("Package file not found: {$path}");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new PackageManagerException("Cannot read file: {$path}");
        }

        $data = json_decode($content, true);
        if ($data === null) {
            throw new PackageManagerException("Invalid JSON in {$path}: " . json_last_error_msg());
        }

        $pkg = new self();
        $pkg->path = $path;
        $pkg->name = $data['name'] ?? '';
        $pkg->version = $data['version'] ?? '0.1.0';
        $pkg->description = $data['description'] ?? '';
        $pkg->author = $data['author'] ?? '';
        $pkg->license = $data['license'] ?? 'CYP-1.0';
        $pkg->dependencies = $data['dependencies'] ?? [];
        $pkg->devDependencies = $data['devDependencies'] ?? [];
        $pkg->scripts = $data['scripts'] ?? [];
        $pkg->extra = $data['extra'] ?? [];

        return $pkg;
    }

    public static function create(string $name, array $options = []): self
    {
        $pkg = new self();
        $pkg->name = $name;
        $pkg->version = $options['version'] ?? '0.1.0';
        $pkg->description = $options['description'] ?? '';
        $pkg->author = $options['author'] ?? '';
        $pkg->license = $options['license'] ?? 'CYP-1.0';
        $pkg->dependencies = $options['dependencies'] ?? [];
        $pkg->devDependencies = $options['devDependencies'] ?? [];
        $pkg->scripts = $options['scripts'] ?? [];
        $pkg->extra = $options['extra'] ?? [];
        return $pkg;
    }

    public function save(?string $path = null): void
    {
        $path = $path ?? $this->path;
        if (empty($path)) {
            $path = getcwd() . DIRECTORY_SEPARATOR . 'cyp.json';
        }

        $data = [
            'name' => $this->name,
            'version' => $this->version,
            'description' => $this->description,
            'author' => $this->author,
            'license' => $this->license,
            'dependencies' => (object)$this->dependencies,
        ];

        if (!empty($this->devDependencies)) {
            $data['devDependencies'] = $this->devDependencies;
        }
        if (!empty($this->scripts)) {
            $data['scripts'] = $this->scripts;
        }
        if (!empty($this->extra)) {
            $data['extra'] = $this->extra;
        }

        $tmp = $path . '.' . uniqid('', true) . '.tmp';
        $written = @file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        if ($written === false) {
            throw new PackageManagerException("Failed to write package file: {$path}");
        }
        if (!rename($tmp, $path)) {
            unlink($tmp);
            throw new PackageManagerException("Failed to rename temporary package file");
        }

        $this->path = $path;
    }

    public function validate(): array
    {
        $errors = [];
        foreach (self::REQUIRED_FIELDS as $field) {
            $val = $this->$field;
            if ($val === null || $val === '' || (is_array($val) && empty($val))) {
                $errors[] = "Missing required field: {$field}";
            }
        }
        if (!preg_match('/^[a-z0-9_\/\-]+$/', $this->name)) {
            $errors[] = "Package name must be lowercase alphanumeric with underscores, slashes, and hyphens only";
        }
        if (str_contains($this->name, '//') || str_ends_with($this->name, '/')) {
            $errors[] = "Package name cannot contain empty segments or end with slash";
        }
        if (!preg_match('/^\d+\.\d+\.\d+(-[a-zA-Z0-9.]+)?(\+[a-zA-Z0-9.]+)?$/', $this->version)) {
            $errors[] = "Version must be in semver format (e.g., 1.0.0, 1.0.0-alpha1, 1.0.0+build)";
        }
        return $errors;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'description' => $this->description,
            'author' => $this->author,
            'license' => $this->license,
            'dependencies' => $this->dependencies,
            'devDependencies' => $this->devDependencies,
            'scripts' => $this->scripts,
            'extra' => $this->extra,
        ];
    }
}
