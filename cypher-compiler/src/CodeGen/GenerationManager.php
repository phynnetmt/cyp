<?php

namespace Cypher\Compiler\CodeGen;

use Cypher\Compiler\AST\ModuleNode;
use Cypher\Compiler\CodeGen\Backend\LaravelGenerator;
use Cypher\Compiler\CodeGen\Frontend\ReactGenerator;
use Cypher\Compiler\CodeGen\Database\PostgresGenerator;
use Cypher\Compiler\CodeGen\Auth\AuthGenerator;
use Cypher\Compiler\CodeGen\Deployment\DeploymentGenerator;

class GenerationManager
{
    private array $config;
    private array $generatedFiles = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function generateAll(ModuleNode $ast): array
    {
        $this->generatedFiles = [];

        // 1. Backend (Laravel)
        $backend = new LaravelGenerator($this->config);
        $this->generatedFiles = array_merge($this->generatedFiles, $backend->generate($ast));

        // 2. Database (PostgreSQL)
        $database = new PostgresGenerator($this->config);
        $this->generatedFiles = array_merge($this->generatedFiles, $database->generate($ast));

        // 3. Frontend (React + TypeScript + Tailwind)
        $frontend = new ReactGenerator($this->config);
        $this->generatedFiles = array_merge($this->generatedFiles, $frontend->generate($ast));

        // 4. Authentication
        $auth = new AuthGenerator($this->config);
        $this->generatedFiles = array_merge($this->generatedFiles, $auth->generate($ast));

        // 5. Deployment
        $deployment = new DeploymentGenerator($this->config);
        $this->generatedFiles = array_merge($this->generatedFiles, $deployment->generate($ast));

        return $this->generatedFiles;
    }

    public function getGeneratedFiles(): array
    {
        return $this->generatedFiles;
    }
}
