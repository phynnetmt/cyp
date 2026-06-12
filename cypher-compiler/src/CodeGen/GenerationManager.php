<?php

namespace Cypher\Compiler\CodeGen;

use Cypher\Compiler\AST\ModuleNode;
use Cypher\Compiler\CodeGen\Backend\LaravelGenerator;
use Cypher\Compiler\CodeGen\Frontend\ReactGenerator;
use Cypher\Compiler\CodeGen\Database\PostgresGenerator;
use Cypher\Compiler\CodeGen\Auth\AuthGenerator;
use Cypher\Compiler\CodeGen\Deployment\DeploymentGenerator;
use Cypher\Compiler\Project\AppProject;

class GenerationManager
{
    private array $config;
    private array $generatedFiles = [];
    private ?AppProject $project;

    public function __construct(array $config = [], ?AppProject $project = null)
    {
        $this->config = $config;
        $this->project = $project;
    }

    public function generateAll(ModuleNode $ast): array
    {
        $this->generatedFiles = [];

        $backend = new LaravelGenerator($this->config, $this->project);
        $this->generatedFiles = array_merge($this->generatedFiles, $backend->generate($ast));

        $database = new PostgresGenerator($this->config, $this->project);
        $this->generatedFiles = array_merge($this->generatedFiles, $database->generate($ast));

        $frontend = new ReactGenerator($this->config, $this->project);
        $this->generatedFiles = array_merge($this->generatedFiles, $frontend->generate($ast));

        $auth = new AuthGenerator($this->config, $this->project);
        $this->generatedFiles = array_merge($this->generatedFiles, $auth->generate($ast));

        $deployment = new DeploymentGenerator($this->config, $this->project);
        $this->generatedFiles = array_merge($this->generatedFiles, $deployment->generate($ast));

        $this->generateBuildManifest();

        return $this->generatedFiles;
    }

    public function getGeneratedFiles(): array
    {
        return $this->generatedFiles;
    }

    private function generateBuildManifest(): void
    {
        $projectName = $this->project?->getConfig()->get('name', 'cyp-project') ?? 'cyp-project';
        $projectVersion = $this->project?->getConfig()->get('version', '0.1.0') ?? '0.1.0';

        $manifest = [
            'generated_by' => 'CYP Compiler v0.1.0',
            'project' => $projectName,
            'version' => $projectVersion,
            'generated_at' => date('c'),
            'file_count' => count($this->generatedFiles),
            'warning' => 'THIS DIRECTORY IS AUTO-GENERATED. DO NOT EDIT GENERATED FILES. Edit .cyp source files instead.',
        ];

        $this->generatedFiles['.cyp-manifest.json'] = json_encode($manifest, JSON_PRETTY_PRINT) . "\n";
    }
}
