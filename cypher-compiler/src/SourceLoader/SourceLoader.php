<?php

namespace Cypher\Compiler\SourceLoader;

class SourceLoader
{
    private array $loadedModules = [];
    private array $searchPaths = [];

    public function __construct(array $searchPaths = [])
    {
        $this->searchPaths = array_merge($searchPaths, [getcwd()]);
    }

    public function load(string $path): LoadedSource
    {
        if (!file_exists($path)) {
            foreach ($this->searchPaths as $base) {
                $full = $base . DIRECTORY_SEPARATOR . $path;
                if (file_exists($full)) {
                    return $this->readFile($full);
                }
                $cypPath = $full . '.cyp';
                if (file_exists($cypPath)) {
                    return $this->readFile($cypPath);
                }
            }
            throw new SourceLoaderException("Module not found: {$path}");
        }
        return $this->readFile($path);
    }

    public function addSearchPath(string $path): void
    {
        $this->searchPaths[] = $path;
    }

    private function readFile(string $path): LoadedSource
    {
        if (isset($this->loadedModules[$path])) {
            return $this->loadedModules[$path];
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new SourceLoaderException("Cannot read file: {$path}");
        }

        $source = new LoadedSource($path, $content);
        $this->loadedModules[$path] = $source;
        return $source;
    }
}
