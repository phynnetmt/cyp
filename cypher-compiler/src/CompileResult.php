<?php

namespace Cypher\Compiler;

use Cypher\Compiler\AST\ModuleNode;
use Cypher\Compiler\Lexer\Token;
use Cypher\Compiler\ErrorHandler\ErrorHandler;
use Cypher\Compiler\Project\AppProject;

class CompileResult
{
    public bool $success = false;
    public ?ModuleNode $ast = null;
    public array $tokens = [];
    public array $generatedFiles = [];
    public array $errors = [];
    public string $sourceText = '';
    public ?string $sourceFilename = null;

    private ErrorHandler $errorHandler;
    public array $sourceFiles = [];
    public array $perFileAsts = [];
    public ?AppProject $project = null;

    public function __construct(?ErrorHandler $errorHandler = null)
    {
        $this->errorHandler = $errorHandler ?? new ErrorHandler();
    }

    public function addError(string $stage, string $message, ?string $file = null): void
    {
        $prefix = $file ? "[{$file}] " : '';
        $this->errors[] = "{$prefix}{$stage}: {$message}";
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function getFormattedErrors(): string
    {
        return $this->errorHandler->formatCompileErrors($this->errors, $this->sourceText);
    }

    public function getOutputDir(): string
    {
        return $this->project?->getConfig()->get('build.output', 'build') ?? 'build';
    }

    public function getFileCount(): int
    {
        return count($this->sourceFiles);
    }

    public function getGeneratedFileCount(): int
    {
        return count($this->generatedFiles);
    }

    public function getSummary(): array
    {
        return [
            'success' => $this->success,
            'sources' => count($this->sourceFiles),
            'generated' => count($this->generatedFiles),
            'errors' => count($this->errors),
        ];
    }
}
