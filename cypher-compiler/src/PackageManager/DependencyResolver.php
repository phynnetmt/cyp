<?php

namespace Cypher\Compiler\PackageManager;

class DependencyResolver
{
    private array $resolved = [];
    private array $errors = [];

    public function resolve(array $dependencies, array $availablePackages = []): array
    {
        $this->resolved = [];
        $this->errors = [];

        foreach ($dependencies as $name => $versionConstraint) {
            $this->resolveDependency($name, $versionConstraint, $availablePackages);
        }

        return $this->resolved;
    }

    private function resolveDependency(string $name, string $constraint, array $available): void
    {
        if (isset($this->resolved[$name])) {
            $existing = $this->resolved[$name];
            if (!$this->satisfies($existing['version'], $constraint)) {
                $this->errors[] = "Conflict: {$name} requires {$constraint} but {$existing['version']} is already resolved";
            }
            return;
        }

        $package = $available[$name] ?? null;
        if (!$package) {
            $this->errors[] = "Package not found: {$name} ({$constraint})";
            return;
        }

        $bestVersion = $this->findBestVersion($package['versions'] ?? [], $constraint);
        if (!$bestVersion) {
            $this->errors[] = "No version of {$name} satisfies {$constraint}";
            return;
        }

        $this->resolved[$name] = [
            'name' => $name,
            'version' => $bestVersion,
            'constraint' => $constraint,
        ];

        $versionData = $package['versions'][$bestVersion] ?? [];
        $subDeps = $versionData['dependencies'] ?? [];
        foreach ($subDeps as $subName => $subConstraint) {
            $this->resolveDependency($subName, $subConstraint, $available);
        }
    }

    public function findBestVersion(array $versions, string $constraint): ?string
    {
        $candidates = array_keys($versions);
        $candidates = array_filter($candidates, fn($v) => $this->satisfies($v, $constraint));
        if (empty($candidates)) return null;
        usort($candidates, fn($a, $b) => version_compare($b, $a));
        return $candidates[0];
    }

    public function satisfies(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);

        if ($constraint === '*' || $constraint === 'latest' || $constraint === 'x') {
            return true;
        }

        if ($constraint === $version) {
            return true;
        }

        // || (OR) constraint - must be checked BEFORE space/AND handling
        if (str_contains($constraint, '||')) {
            $parts = explode('||', $constraint);
            foreach ($parts as $part) {
                if ($this->satisfies($version, trim($part))) return true;
            }
            return false;
        }

        // Hyphen range: 1.0.0 - 2.0.0 (must be checked BEFORE space-separated AND)
        if (str_contains($constraint, ' - ')) {
            $parts = explode(' - ', $constraint, 2);
            $from = trim($parts[0]);
            $to = trim($parts[1]);
            return version_compare($version, $from, '>=') && version_compare($version, $to, '<=');
        }

        // Space-separated AND constraints (e.g., ">=1.0 <2.0")
        if (str_contains($constraint, ' ') && !str_starts_with($constraint, '^') && !str_starts_with($constraint, '~')) {
            $parts = preg_split('/\s+/', $constraint);
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p === '') continue;
                $op = '';
                $ver = '';
                if (str_starts_with($p, '>=')) { $op = '>='; $ver = substr($p, 2); }
                elseif (str_starts_with($p, '<=')) { $op = '<='; $ver = substr($p, 2); }
                elseif (str_starts_with($p, '>')) { $op = '>'; $ver = substr($p, 1); }
                elseif (str_starts_with($p, '<')) { $op = '<'; $ver = substr($p, 1); }
                elseif (str_starts_with($p, '!=')) { $op = '!='; $ver = substr($p, 2); }
                else { return false; }
                if (!version_compare($version, $ver, $op)) return false;
            }
            return true;
        }

        // Caret ^1.2.3
        // ^1.2.3 => >=1.2.3 <2.0.0
        // ^0.3.0 => >=0.3.0 <0.4.0
        // ^0.0.3 => >=0.0.3 <0.0.4
        if (str_starts_with($constraint, '^')) {
            $base = ltrim($constraint, '^');
            $parts = explode('.', $base);
            $major = (int)($parts[0] ?? 0);
            $minor = (int)($parts[1] ?? 0);
            $patch = (int)($parts[2] ?? 0);
            if ($major > 0) {
                $upper = ($major + 1) . '.0.0';
            } elseif ($minor > 0) {
                $upper = "0.{$minor}.0";
                $upper = '0.' . ($minor + 1) . '.0';
            } else {
                $upper = '0.0.' . ($patch + 1);
            }
            return version_compare($version, $base, '>=') && version_compare($version, $upper, '<');
        }

        // Tilde ~1.2.3
        // ~1.2.3 => >=1.2.3 <1.3.0
        // ~1.2   => >=1.2.0 <1.3.0
        // ~1     => >=1.0.0 <2.0.0
        if (str_starts_with($constraint, '~')) {
            $base = ltrim($constraint, '~');
            $parts = explode('.', $base);
            $major = (int)($parts[0] ?? 0);
            $minor = (int)($parts[1] ?? 0);
            if (count($parts) >= 2) {
                $upper = "{$major}." . ($minor + 1) . '.0';
            } else {
                $upper = ($major + 1) . '.0.0';
            }
            $lower = $base;
            if (count($parts) === 1) $lower .= '.0.0';
            elseif (count($parts) === 2) $lower .= '.0';
            return version_compare($version, $lower, '>=') && version_compare($version, $upper, '<');
        }

        // Simple operator constraints >=1.0, <=2.0, >1.5, <2.0, !=1.0
        if (str_starts_with($constraint, '>=') || str_starts_with($constraint, '<=') ||
            str_starts_with($constraint, '>') || str_starts_with($constraint, '<') ||
            str_starts_with($constraint, '!=')) {
            $op = '';
            if (str_starts_with($constraint, '>=')) $op = '>=';
            elseif (str_starts_with($constraint, '<=')) $op = '<=';
            elseif (str_starts_with($constraint, '!=')) $op = '!=';
            elseif (str_starts_with($constraint, '>')) $op = '>';
            elseif (str_starts_with($constraint, '<')) $op = '<';
            $ver = substr($constraint, strlen($op));
            return version_compare($version, trim($ver), $op);
        }

        // Wildcard: 1.* or 1.x
        if (str_ends_with($constraint, '.*') || str_ends_with($constraint, '.x')) {
            $prefix = rtrim($constraint, '.*x');
            return str_starts_with($version, $prefix);
        }

        return false;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function getResolved(): array
    {
        return $this->resolved;
    }
}
