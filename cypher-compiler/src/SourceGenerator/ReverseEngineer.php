<?php

namespace Cypher\Compiler\SourceGenerator;

class ReverseEngineer
{
    private SourceGenerator $generator;
    private array $detected = [];

    public function __construct(?SourceGenerator $generator = null)
    {
        $this->generator = $generator ?? new SourceGenerator(getcwd());
    }

    public function importLaravel(string $projectPath): array
    {
        $files = [];
        $modelsPath = $projectPath . '/app/Models';

        if (is_dir($modelsPath)) {
            foreach (glob($modelsPath . '/*.php') as $modelFile) {
                $content = file_get_contents($modelFile);
                $modelName = pathinfo($modelFile, PATHINFO_FILENAME);
                $fields = $this->extractLaravelFields($content);
                $relationships = $this->extractLaravelRelationships($content);

                $cypCode = $this->generator->generateModel($modelName, $fields, $relationships);
                $files["models/" . strtolower($modelName) . ".cyp"] = $cypCode;
                $this->detected[] = "model: {$modelName}";
            }
        }

        $routesPath = $projectPath . '/routes';
        if (is_dir($routesPath)) {
            foreach (glob($routesPath . '/*.php') as $routeFile) {
                $content = file_get_contents($routeFile);
                $routes = $this->extractLaravelRoutes($content);
                foreach ($routes as $route) {
                    $cleanPath = trim(str_replace(['/', '{', '}', '-'], '_', ltrim($route['path'], '/')));
                    $cypCode = $this->generator->generateApi(
                        $route['method'],
                        $route['path'],
                        [['type' => 'return', 'value' => '// Imported from Laravel']]
                    );
                    $files["api/" . ($cleanPath ?: 'root') . ".cyp"] = $cypCode;
                    $this->detected[] = "api: {$route['method']} {$route['path']}";
                }
            }
        }

        $controllersPath = $projectPath . '/app/Http/Controllers';
        if (is_dir($controllersPath)) {
            $controllers = $this->findControllers($controllersPath);
            foreach ($controllers as $ctrl) {
                $this->detected[] = "controller: {$ctrl}";
            }
        }

        return $files;
    }

    public function importReact(string $projectPath): array
    {
        $files = [];
        $srcPath = $projectPath . '/src';

        if (!is_dir($srcPath)) {
            $srcPath = $projectPath;
        }

        foreach (glob($srcPath . '/pages/**/*.tsx') as $pageFile) {
            $pageName = pathinfo($pageFile, PATHINFO_FILENAME);
            $content = file_get_contents($pageFile);
            $sayLines = $this->extractJSXText($content);

            $cypCode = $this->generator->generatePage($pageName, $sayLines);
            $files["pages/" . strtolower($pageName) . ".cyp"] = $cypCode;
            $this->detected[] = "page: {$pageName}";
        }

        foreach (glob($srcPath . '/components/**/*.tsx') as $compFile) {
            $compName = pathinfo($compFile, PATHINFO_FILENAME);
            $cypCode = $this->generator->generateComponent($compName, [], [
                ['type' => 'say', 'value' => "<!-- Imported from React: {$compName} -->"],
            ]);
            $files["components/" . strtolower($compName) . ".cyp"] = $cypCode;
            $this->detected[] = "component: {$compName}";
        }

        return $files;
    }

    public function importDirectory(string $path): array
    {
        $files = [];

        if (!is_dir($path)) {
            return $files;
        }

        if (file_exists($path . '/artisan')) {
            $laravelFiles = $this->importLaravel($path);
            $files = array_merge($files, $laravelFiles);
        }

        if (file_exists($path . '/package.json')) {
            $pkg = json_decode(file_get_contents($path . '/package.json'), true);
            $deps = array_keys($pkg['dependencies'] ?? []);
            if (in_array('react', $deps) || in_array('next', $deps)) {
                $reactFiles = $this->importReact($path);
                $files = array_merge($files, $reactFiles);
            }
        }

        return $files;
    }

    public function getDetected(): array
    {
        return $this->detected;
    }

    private function extractLaravelFields(string $content): array
    {
        $fields = [];
        if (preg_match('/protected\s+\$fillable\s*=\s*\[([^\]]+)\]/s', $content, $m)) {
            $names = preg_split('/[\s,]+/', trim($m[1]));
            foreach ($names as $name) {
                $name = trim($name, "'\" ");
                if (!empty($name)) {
                    $fields[] = ['name' => $name, 'type' => 'string'];
                }
            }
        }
        return $fields;
    }

    private function extractLaravelRelationships(string $content): array
    {
        $rels = [];
        $patterns = [
            '/function\s+(\w+)\s*\(\s*\)\s*:\s*(BelongsTo|HasMany|HasOne|BelongsToMany)/',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $rels[] = [
                        'name' => $m[1],
                        'type' => $m[2],
                        'target' => 'RelatedModel',
                    ];
                }
            }
        }
        return $rels;
    }

    private function extractLaravelRoutes(string $content): array
    {
        $routes = [];
        $patterns = [
            '/Route::(get|post|put|patch|delete)\s*\(\s*[\'"]([^\'"]+)[\'"]/',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $routes[] = [
                        'method' => strtoupper($m[1]),
                        'path' => $m[2],
                    ];
                }
            }
        }
        return $routes;
    }

    private function extractJSXText(string $content): array
    {
        $lines = [];
        if (preg_match_all('/<([a-zA-Z][a-zA-Z0-9]*)[^>]*>(.*?)<\/\1>/s', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $text = trim(strip_tags($m[2]));
                if (!empty($text)) {
                    $lines[] = ['type' => 'say', 'value' => htmlspecialchars($text, ENT_QUOTES)];
                }
            }
        }
        if (empty($lines)) {
            $lines[] = ['type' => 'say', 'value' => '<!-- Imported component -->'];
        }
        return $lines;
    }

    private function findControllers(string $path): array
    {
        $controllers = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $controllers[] = str_replace([$path . DIRECTORY_SEPARATOR, '.php'], '', $file->getPathname());
            }
        }
        return $controllers;
    }
}
