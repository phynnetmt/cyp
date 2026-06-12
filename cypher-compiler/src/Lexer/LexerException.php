<?php

namespace Cypher\Compiler\Lexer;

class LexerException extends \RuntimeException
{
    public readonly int $lineNumber;
    public readonly int $columnNumber;

    public function __construct(
        string $message,
        int $line = 0,
        int $column = 0,
    ) {
        $this->lineNumber = $line;
        $this->columnNumber = $column;
        parent::__construct(sprintf('[%d:%d] %s', $line, $column, $message));
    }
}
