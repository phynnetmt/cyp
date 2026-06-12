<?php

namespace Cypher\Compiler\PackageManager;

use Cypher\Compiler\Registry\RegistryClient;

class PackageManager
{
    private DependencyResolver $resolver;
    private LockFile $lockFile;
    private array $config;
    private string $vendorDir;
    private ?RegistryClient $registry = null;

    public function __construct(array $config = [])
    {
        $this->resolver = new DependencyResolver();
        $this->lockFile = new LockFile();
        $this->config = $config;
        $this->vendorDir = $config['vendor_dir'] ?? (getcwd() . DIRECTORY_SEPARATOR . 'vendor');
        if (!empty($config['registry_url'])) {
            $this->registry = new RegistryClient($config['registry_url']);
        }
    }

    public function install(?string $packageName = null, ?string $version = null): array
    {
        $cwd = getcwd();
        $pkgPath = $cwd . DIRECTORY_SEPARATOR . 'cyp.json';

        if (!file_exists($pkgPath)) {
            throw new PackageManagerException("No cyp.json found. Run 'cyp new' first.");
        }

        $pkg = PackageJson::load($pkgPath);

        if ($packageName) {
            $constraint = $version ?? '*';
            $pkg->dependencies[$packageName] = $constraint;
        }

        $errors = $pkg->validate();
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'installed' => []];
        }

        $lockPath = $cwd . DIRECTORY_SEPARATOR . 'cyp.lock';
        $this->lockFile = LockFile::load($lockPath);

        // Fetch available packages from registry
        $availablePackages = $this->fetchAvailablePackages($pkg->dependencies);

        $result = $this->resolver->resolve($pkg->dependencies, $availablePackages);

        if ($this->resolver->hasErrors()) {
            return [
                'success' => false,
                'errors' => $this->resolver->getErrors(),
                'installed' => [],
            ];
        }

        $installed = [];
        foreach ($result as $name => $info) {
            $this->installPackage($name, $info['version']);
            $this->lockFile->addPackage($name, $info['version'], $info['constraint'], []);
            $installed[] = $name;
        }

        $this->lockFile->save($lockPath);

        if ($packageName) {
            $pkg->save($pkgPath);
        }

        return ['success' => true, 'installed' => $installed, 'errors' => []];
    }

    public function update(?string $packageName = null): array
    {
        $cwd = getcwd();
        $pkgPath = $cwd . DIRECTORY_SEPARATOR . 'cyp.json';

        if (!file_exists($pkgPath)) {
            throw new PackageManagerException("No cyp.json found.");
        }

        $pkg = PackageJson::load($pkgPath);
        $deps = $packageName
            ? [$packageName => $pkg->dependencies[$packageName] ?? '*']
            : $pkg->dependencies;

        $lockPath = $cwd . DIRECTORY_SEPARATOR . 'cyp.lock';
        $this->lockFile = LockFile::load($lockPath);

        foreach ($deps as $name => $constraint) {
            $this->lockFile->removePackage($name);
        }

        // Save the cleared lock before install reloads it
        $this->lockFile->save($lockPath);

        return $this->install();
    }

    public function remove(string $packageName): array
    {
        $cwd = getcwd();
        $pkgPath = $cwd . DIRECTORY_SEPARATOR . 'cyp.json';

        if (!file_exists($pkgPath)) {
            throw new PackageManagerException("No cyp.json found.");
        }

        $pkg = PackageJson::load($pkgPath);
        $found = false;

        if (isset($pkg->dependencies[$packageName])) {
            unset($pkg->dependencies[$packageName]);
            $found = true;
        }
        if (isset($pkg->devDependencies[$packageName])) {
            unset($pkg->devDependencies[$packageName]);
            $found = true;
        }

        if (!$found) {
            throw new PackageManagerException("Package '{$packageName}' not found in cyp.json");
        }

        $pkg->save($pkgPath);

        $packageDir = $this->vendorDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $packageName);
        if (is_dir($packageDir)) {
            $this->rmdir($packageDir);
        }

        $lockPath = $cwd . DIRECTORY_SEPARATOR . 'cyp.lock';
        if (file_exists($lockPath)) {
            $this->lockFile = LockFile::load($lockPath);
            $this->lockFile->removePackage($packageName);
            if (empty($this->lockFile->getPackages())) {
                unlink($lockPath);
            } else {
                $this->lockFile->save($lockPath);
            }
        }

        return ['success' => true, 'removed' => $packageName];
    }

    public function listInstalled(): array
    {
        $cwd = getcwd();
        $lockPath = $cwd . DIRECTORY_SEPARATOR . 'cyp.lock';

        if (!file_exists($lockPath)) {
            return [];
        }

        $this->lockFile = LockFile::load($lockPath);
        return $this->lockFile->getPackages();
    }

    public function getInstalled(): array
    {
        $packages = $this->listInstalled();
        $result = [];
        foreach ($packages as $name => $info) {
            $result[$name] = $info['version'] ?? '0.0.0';
        }
        return $result;
    }

    public function getInstalledCount(): int
    {
        return count($this->listInstalled());
    }

    private function fetchAvailablePackages(array $dependencies): array
    {
        $available = [];

        if ($this->registry) {
            foreach ($dependencies as $name => $constraint) {
                $pkgData = $this->registry->fetchPackage($name);
                if ($pkgData) {
                    $available[$name] = $pkgData;
                }
            }
        }

        return $available;
    }

    private function installPackage(string $name, string $version): void
    {
        $targetDir = $this->vendorDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        // Fetch and extract package content if registry available
        if ($this->registry) {
            $pkgData = $this->registry->fetchPackage($name, $version);
            if ($pkgData && !empty($pkgData['dist'])) {
                $this->downloadPackage($pkgData['dist'], $targetDir);
            }
        }

        $meta = [
            'name' => $name,
            'version' => $version,
            'installed_at' => date('c'),
        ];
        $metaPath = $targetDir . DIRECTORY_SEPARATOR . '.cyp-installed';
        $written = file_put_contents($metaPath, json_encode($meta, JSON_PRETTY_PRINT));
        if ($written === false) {
            throw new PackageManagerException("Failed to write metadata for {$name}");
        }
    }

    private function downloadPackage(string $url, string $targetDir): void
    {
        $context = stream_context_create(['http' => ['timeout' => 30, 'user_agent' => 'CypherCLI/0.4.0']]);
        $archive = @file_get_contents($url, false, $context);
        if ($archive === false) return;

        $tmpFile = tempnam(sys_get_temp_dir(), 'cyp_archive_');
        file_put_contents($tmpFile, $archive);

        $phar = new \PharData($tmpFile);
        $phar->extractTo($targetDir, null, true);

        unlink($tmpFile);
    }

    public function installSamplePackages(): array
    {
        $samples = [
            'cyp/std' => ['version' => '1.0.0', 'description' => 'CYP Standard Library'],
            'cyp/string' => ['version' => '1.0.0', 'description' => 'String manipulation utilities'],
            'cyp/http' => ['version' => '1.0.0', 'description' => 'HTTP client and server'],
            'cyp/json' => ['version' => '1.0.0', 'description' => 'JSON encoding/decoding'],
        ];

        // Update cyp.json dependencies
        $pkgPath = getcwd() . DIRECTORY_SEPARATOR . 'cyp.json';
        if (file_exists($pkgPath)) {
            $pkg = PackageJson::load($pkgPath);
            foreach ($samples as $name => $info) {
                $pkg->dependencies[$name] = '^' . $info['version'];
            }
            $pkg->save($pkgPath);
        }

        $installed = [];
        foreach ($samples as $name => $info) {
            $this->installPackage($name, $info['version']);
            $this->lockFile->addPackage($name, $info['version'], '*', []);
            $installed[] = $name;
        }

        $lockPath = getcwd() . DIRECTORY_SEPARATOR . 'cyp.lock';
        $this->lockFile->save($lockPath);

        return $installed;
    }

    private function rmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($dir);
    }
}
