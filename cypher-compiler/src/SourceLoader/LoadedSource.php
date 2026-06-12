<?php

namespace Cypher\Compiler\SourceLoader;

class LoadedSource
{
    public readonly string $path;
    public readonly string $content;
    public readonly array $lines;

    public function __construct(string $path, string $content)
    {
        $this->path = $path;
        $this->content = $content;
        $this->lines = explode("\n", $content);
    }

    public function getLine(int $number): string
    {
        return $this->lines[$number - 1] ?? '';
    }

    public function getSnippet(int $line, int $padding = 2): string
    {
        $start = max(1, $line - $padding);
        $end = min(count($this->lines), $line + $padding);
        $result = '';
        for ($i = $start; $i <= $end; $i++) {
            $marker = ($i === $line) ? '>>' : '  ';
            $result .= sprintf("%s %3d | %s\n", $marker, $i, rtrim($this->getLine($i)));
        }
        return $result;
    }
}
