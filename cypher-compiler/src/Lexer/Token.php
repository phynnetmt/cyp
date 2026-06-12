<?php

namespace Cypher\Compiler\Lexer;

class Token
{
    public function __construct(
        public readonly TokenType $type,
        public readonly string $value,
        public readonly int $line,
        public readonly int $column,
    ) {}

    public function __toString(): string
    {
        return sprintf('Token(%s, "%s", %d:%d)', $this->type->value, $this->value, $this->line, $this->column);
    }
}
