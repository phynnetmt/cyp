<?php

namespace Cypher\Compiler;

use Cypher\Compiler\Lexer\Lexer;
use Cypher\Compiler\Parser\Parser;
use Cypher\Compiler\Semantic\SemanticAnalyzer;
use Cypher\Compiler\TypeChecker\TypeChecker;
use Cypher\Compiler\Optimizer\Optimizer;
use Cypher\Compiler\CodeGen\GenerationManager;
use Cypher\Compiler\SourceLoader\SourceLoader;
use Cypher\Compiler\ErrorHandler\ErrorHandler;
use Cypher\Compiler\Project\AppProject;

class Compiler
{
    private array $config;
    private SourceLoader $sourceLoader;
    private ErrorHandler $errorHandler;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->sourceLoader = new SourceLoader($config['search_paths'] ?? []);
        $this->errorHandler = new ErrorHandler();
    }

    public function compileProject(AppProject $project): CompileResult
    {
        $result = new CompileResult($this->errorHandler);
        $result->project = $project;

        $sources = $this->sourceLoader->loadProject($project);

        if (empty($sources)) {
            $result->addError('Project', 'No .cyp files found in project');
            return $result;
        }

        $result->sourceFiles = array_keys($sources);
        $aggregatedStmts = [];

        foreach ($sources as $relativePath => $source) {
            $fileResult = $this->compileSource($source->content, $source->path);

            if ($fileResult->hasErrors()) {
                foreach ($fileResult->errors as $err) {
                    $result->addError('Compile', $err, $relativePath);
                }
                return $result;
            }

            if ($fileResult->ast !== null) {
                $result->perFileAsts[$relativePath] = $fileResult->ast;
                foreach ($fileResult->ast->statements as $stmt) {
                    $aggregatedStmts[] = $stmt;
                }
            }
        }

        $moduleClass = \Cypher\Compiler\AST\ModuleNode::class;
        $aggregatedAst = new $moduleClass($aggregatedStmts);
        $result->ast = $aggregatedAst;

        try {
            $analyzer = new SemanticAnalyzer();
            $errors = $analyzer->analyze($aggregatedAst);
            foreach ($errors as $err) {
                $result->addError('Semantic', "[{$err['line']}:{$err['column']}] {$err['message']}");
            }
            if ($analyzer->hasErrors()) {
                return $result;
            }
        } catch (\Exception $e) {
            $result->addError('Semantic', $e->getMessage());
            return $result;
        }

        try {
            $typeChecker = new TypeChecker();
            $typeErrors = $typeChecker->check($aggregatedAst);
            foreach ($typeErrors as $err) {
                $result->addError('Type', "[{$err['line']}:{$err['column']}] {$err['message']}");
            }
        } catch (\Exception $e) {
            $result->addError('Type', $e->getMessage());
            return $result;
        }

        try {
            $optimizer = new Optimizer();
            $aggregatedAst = $optimizer->optimize($aggregatedAst);
            $result->ast = $aggregatedAst;
        } catch (\Exception $e) {
            $result->addError('Optimizer', $e->getMessage());
            return $result;
        }

        try {
            $generationMode = $this->config['generation_mode'] ?? 'full-stack';
            $manager = new GenerationManager($this->config, $project);
            $files = $manager->generateAll($aggregatedAst);
            $result->generatedFiles = $files;
        } catch (\Exception $e) {
            $result->addError('CodeGen', $e->getMessage());
            return $result;
        }

        $result->success = true;
        return $result;
    }

    public function compile(string $sourceCode, ?string $filename = null): CompileResult
    {
        return $this->compileSource($sourceCode, $filename);
    }

    public function compileFile(string $filePath): CompileResult
    {
        if (!file_exists($filePath)) {
            $result = new CompileResult($this->errorHandler);
            $result->addError('IO', "File not found: {$filePath}");
            return $result;
        }

        $source = @file_get_contents($filePath);
        return $this->compileSource($source, $filePath);
    }

    private function compileSource(string $sourceCode, ?string $filename = null): CompileResult
    {
        $result = new CompileResult($this->errorHandler);
        $result->sourceText = $sourceCode;

        try {
            $lexer = new Lexer($sourceCode);
            $tokens = $lexer->tokenize();
            $result->tokens = $tokens;
        } catch (\Exception $e) {
            $result->addError('Lexer', $e->getMessage());
            return $result;
        }

        try {
            $parser = new Parser($tokens);
            $ast = $parser->parse();
            $result->ast = $ast;
        } catch (\Exception $e) {
            $result->addError('Parser', $e->getMessage());
            return $result;
        }

        try {
            $analyzer = new SemanticAnalyzer();
            $errors = $analyzer->analyze($ast);
            foreach ($errors as $err) {
                $result->addError('Semantic', "[{$err['line']}:{$err['column']}] {$err['message']}");
            }
            if ($analyzer->hasErrors()) {
                return $result;
            }
        } catch (\Exception $e) {
            $result->addError('Semantic', $e->getMessage());
            return $result;
        }

        try {
            $typeChecker = new TypeChecker();
            $typeErrors = $typeChecker->check($ast);
            foreach ($typeErrors as $err) {
                $result->addError('Type', "[{$err['line']}:{$err['column']}] {$err['message']}");
            }
        } catch (\Exception $e) {
            $result->addError('Type', $e->getMessage());
        }

        try {
            $optimizer = new Optimizer();
            $ast = $optimizer->optimize($ast);
            $result->ast = $ast;
        } catch (\Exception $e) {
            $result->addError('Optimizer', $e->getMessage());
        }

        try {
            $generationMode = $this->config['generation_mode'] ?? 'full-stack';
            if ($generationMode === 'full-stack') {
                $manager = new GenerationManager($this->config);
                $files = $manager->generateAll($ast);
            } else {
                $generator = new CodeGen\PhpGenerator($this->config);
                $files = $generator->generate($ast);
            }
            $result->generatedFiles = $files;
        } catch (\Exception $e) {
            $result->addError('CodeGen', $e->getMessage());
            return $result;
        }

        $result->success = true;
        return $result;
    }
}
