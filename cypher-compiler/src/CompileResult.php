<?php

namespace Cypher\Compiler;

use Cypher\Compiler\AST\ModuleNode;
use Cypher\Compiler\Lexer\Token;
use Cypher\Compiler\ErrorHandler\ErrorHandler;

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

    public function __construct(?ErrorHandler $errorHandler = null)
    {
        $this->errorHandler = $errorHandler ?? new ErrorHandler();
    }

    public function addError(string $stage, string $message): void
    {
        $this->errors[] = "{$stage}: {$message}";
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
        return 'build';
    }
}
