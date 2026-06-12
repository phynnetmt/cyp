<?php

namespace Cypher\Compiler\AST;

interface Node
{
    public function getLine(): int;
    public function getColumn(): int;
    public function getType(): string;
}
