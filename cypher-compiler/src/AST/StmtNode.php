<?php

namespace Cypher\Compiler\AST;

abstract class StmtNode implements Node
{
    public function __construct(
        protected readonly int $line,
        protected readonly int $column,
    ) {}

    public function getLine(): int { return $this->line; }
    public function getColumn(): int { return $this->column; }
}
