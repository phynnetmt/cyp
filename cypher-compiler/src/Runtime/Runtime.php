<?php

namespace Cypher\Compiler\Runtime;

use Cypher\Compiler\Compiler;
use Cypher\Compiler\CompileResult;
use Cypher\Compiler\Interpreter\AstInterpreter;
use Cypher\Compiler\Interpreter\InterpreterResult;

class Runtime
{
    private Compiler $compiler;
    private array $options;
    private AstInterpreter $interpreter;

    public function __construct(array $options = [])
    {
        $this->compiler = new Compiler($options);
        $this->options = $options;
        $this->interpreter = new AstInterpreter();
    }

    public function execute(string $sourceCode, ?string $filename = null): RuntimeResult
    {
        $result = new RuntimeResult();
        $compileResult = $this->compiler->compile($sourceCode);

        if ($compileResult->hasErrors()) {
            $result->errors = $compileResult->errors;
            $result->success = false;
            return $result;
        }

        if ($compileResult->ast === null) {
            $result->errors[] = 'Runtime: No AST produced';
            $result->success = false;
            return $result;
        }

        try {
            $interpreterResult = $this->interpreter->interpret($compileResult->ast);

            if ($interpreterResult->hasErrors()) {
                $result->errors[] = 'Runtime: ' . $interpreterResult->error;
                $result->success = false;
                return $result;
            }

            $result->output = $interpreterResult->getOutput();
            $result->success = true;
            return $result;
        } catch (\Throwable $e) {
            // Fallback to PHP code generation if AST execution fails
            $result->errors[] = 'Runtime: ' . $e->getMessage();
            $result->success = false;
            return $result;
        }
    }

    public function executeFile(string $path): RuntimeResult
    {
        if (!file_exists($path)) {
            $result = new RuntimeResult();
            $result->errors[] = "File not found: {$path}";
            $result->success = false;
            return $result;
        }

        $source = @file_get_contents($path);
        return $this->execute($source, $path);
    }

    public function executeNative(string $sourceCode, ?string $filename = null): RuntimeResult
    {
        $result = new RuntimeResult();
        $compileResult = $this->compiler->compile($sourceCode);

        if ($compileResult->hasErrors()) {
            $result->errors = $compileResult->errors;
            $result->success = false;
            return $result;
        }

        if ($compileResult->ast === null) {
            $result->errors[] = 'Runtime: No AST produced';
            $result->success = false;
            return $result;
        }

        $startTime = microtime(true);
        $interpreterResult = $this->interpreter->interpret($compileResult->ast);
        $duration = (microtime(true) - $startTime) * 1000;

        if ($interpreterResult->hasErrors()) {
            $result->errors[] = 'Runtime: ' . $interpreterResult->error;
            $result->success = false;
            return $result;
        }

        $result->output = $interpreterResult->getOutput();
        $result->success = true;
        return $result;
    }
}
