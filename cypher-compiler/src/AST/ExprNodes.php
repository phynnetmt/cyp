<?php

namespace Cypher\Compiler\AST;

class LiteralExpr extends ExprNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly mixed $value,
        public readonly string $literalType,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'LiteralExpr'; }
}

class IdentifierExpr extends ExprNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly string $name,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'IdentifierExpr'; }
}

class BinaryExpr extends ExprNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly ExprNode $left,
        public readonly string $operator,
        public readonly ExprNode $right,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'BinaryExpr'; }
}

class UnaryExpr extends ExprNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly string $operator,
        public readonly ExprNode $operand,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'UnaryExpr'; }
}

class CallExpr extends ExprNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly string $callee,
        public readonly array $arguments,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'CallExpr'; }
}

class PropertyAccessExpr extends ExprNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly ExprNode $object,
        public readonly string $property,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'PropertyAccessExpr'; }
}

class IndexExpr extends ExprNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly ExprNode $target,
        public readonly ExprNode $index,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'IndexExpr'; }
}

class ArrayExpr extends ExprNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly array $elements,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'ArrayExpr'; }
}

class RecordExpr extends ExprNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly array $fields,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'RecordExpr'; }
}

class FieldExpr extends ExprNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly string $name,
        public readonly ExprNode $value,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'FieldExpr'; }
}

class MatchExpr extends ExprNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly ExprNode $subject,
        public readonly array $arms,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'MatchExpr'; }
}

class MatchArm
{
    public function __construct(
        public readonly ExprNode $pattern,
        public readonly ExprNode $value,
    ) {}
}

class LambdaExpr extends ExprNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly array $params,
        public readonly ExprNode $body,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'LambdaExpr'; }
}

class EmbedExpr extends ExprNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly string $content,
        public readonly string $lang,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'EmbedExpr'; }
}

class TernaryExpr extends ExprNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly ExprNode $condition,
        public readonly ExprNode $thenExpr,
        public readonly ExprNode $elseExpr,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'TernaryExpr'; }
}

class InterpolatedStringExpr extends ExprNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly array $parts,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'InterpolatedStringExpr'; }
}

class StringPart
{
    public function __construct(
        public readonly bool $isExpr,
        public readonly string $value,
        public readonly ?ExprNode $expression = null,
    ) {}
}
