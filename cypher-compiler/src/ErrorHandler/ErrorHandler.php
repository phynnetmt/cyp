<?php

namespace Cypher\Compiler\ErrorHandler;

class ErrorHandler
{
    public function formatError(string $stage, string $message, ?string $source = null, int $line = 0, int $column = 0): string
    {
        $output = "\n  \033[31m✗ {$stage} Error\033[0m\n";
        $output .= "    {$message}\n";

        if ($source && $line > 0) {
            $lines = explode("\n", $source);
            $start = max(0, $line - 3);
            $end = min(count($lines), $line + 2);

            for ($i = $start; $i < $end; $i++) {
                $num = $i + 1;
                $isTarget = ($num === $line);
                $prefix = $isTarget ? '  >>' : '    ';
                $output .= "{$prefix} {$num} | {$lines[$i]}\n";

                if ($isTarget && $column > 0) {
                    // Calculate visual column accounting for possible tabs
                    $caretPos = strlen((string)$num) + 2 + $column;
                    $output .= "    " . str_repeat(' ', $caretPos) . "^\n";
                }
            }
        }

        return $output;
    }

    public function formatCompileErrors(array $errors, ?string $source = null): string
    {
        if (empty($errors)) return '';

        $output = "\n\033[1;31mCompilation failed with " . count($errors) . " error(s):\033[0m\n";
        foreach ($errors as $err) {
            $output .= "  \033[31m●\033[0m {$err}\n";
        }
        if ($source) {
            $lines = explode("\n", $source);
            $output .= "\n\033[33m--- Source Context ---\033[0m\n";
            $total = count($lines);
            $start = max(0, $total - 5);
            for ($i = $start; $i < $total; $i++) {
                $num = $i + 1;
                $output .= "  {$num} | {$lines[$i]}\n";
            }
        }
        return $output;
    }
}
