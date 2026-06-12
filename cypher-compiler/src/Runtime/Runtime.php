<?php

namespace Cypher\Compiler\Runtime;

use Cypher\Compiler\Compiler;
use Cypher\Compiler\CompileResult;

class Runtime
{
    private Compiler $compiler;
    private array $options;

    public function __construct(array $options = [])
    {
        $this->compiler = new Compiler($options);
        $this->options = $options;
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

        $phpCode = $compileResult->generatedFiles['app/App.php'] ?? '';
        if (empty($phpCode)) {
            $firstFile = reset($compileResult->generatedFiles);
            if (is_string($firstFile)) {
                $phpCode = $firstFile;
            }
        }

        if (empty($phpCode)) {
            $result->errors[] = 'Runtime: No executable code generated';
            $result->success = false;
            return $result;
        }

        try {
            $tmpFile = tempnam(sys_get_temp_dir(), 'cyp_') . '.php';
            // Generated code already contains <?php tag
            file_put_contents($tmpFile, $phpCode);

            ob_start();
            include $tmpFile;
            $output = ob_get_clean();

            unlink($tmpFile);

            $result->output = $output;
            $result->success = true;
        } catch (\Throwable $e) {
            $result->errors[] = 'Runtime: ' . $e->getMessage();
            $result->success = false;
        }

        return $result;
    }

    public function executeFile(string $path): RuntimeResult
    {
        if (!file_exists($path)) {
            $result = new RuntimeResult();
            $result->errors[] = "File not found: {$path}";
            $result->success = false;
            return $result;
        }

        $source = file_get_contents($path);
        return $this->execute($source, $path);
    }
}
