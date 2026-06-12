<?php

namespace Cypher\Compiler\AST;

class VarDeclStmt extends StmtNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly string $name,
        public readonly ?string $typeHint,
        public readonly ExprNode $initializer,
        public readonly bool $isMutable = true,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'VarDeclStmt'; }
}

class AssignStmt extends StmtNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly ExprNode $target,
        public readonly ExprNode $value,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'AssignStmt'; }
}

class SayStmt extends StmtNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly ExprNode $expression,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'SayStmt'; }
}

class IfStmt extends StmtNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly ExprNode $condition,
        public readonly array $thenBody,
        public readonly ?StmtNode $elseIf,
        public readonly ?array $elseBody,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'IfStmt'; }
}

class WhileStmt extends StmtNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly ExprNode $condition,
        public readonly array $body,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'WhileStmt'; }
}

class RepeatStmt extends StmtNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly ExprNode $count,
        public readonly array $body,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'RepeatStmt'; }
}

class ForStmt extends StmtNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly string $variable,
        public readonly ExprNode $iterable,
        public readonly array $body,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'ForStmt'; }
}

class ReturnStmt extends StmtNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly ?ExprNode $value,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'ReturnStmt'; }
}

class TaskDeclStmt extends StmtNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly string $name,
        public readonly array $params,
        public readonly ?string $returnType,
        public readonly array $body,
        public readonly array $modifiers = [],
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'TaskDeclStmt'; }
}

class FuncDeclStmt extends StmtNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly string $name,
        public readonly array $params,
        public readonly ?string $returnType,
        public readonly array $body,
        public readonly array $modifiers = [],
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'FuncDeclStmt'; }
}

class ParamDecl
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $typeHint,
        public readonly ?ExprNode $defaultValue = null,
    ) {}
}

class ModelDeclStmt extends StmtNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly string $name,
        public readonly array $fields,
        public readonly array $relationships = [],
        public readonly array $options = [],
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'ModelDeclStmt'; }
}

class ModelField
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly array $attributes = [],
    ) {}
}

class ModelRelationship
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $target,
        public readonly string $foreignKey,
    ) {}
}

class PageDeclStmt extends StmtNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly string $name,
        public readonly array $body,
        public readonly array $options = [],
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'PageDeclStmt'; }
}

class ApiDeclStmt extends StmtNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly string $path,
        public readonly string $method,
        public readonly array $body,
        public readonly array $options = [],
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'ApiDeclStmt'; }
}

class ComponentDeclStmt extends StmtNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly string $name,
        public readonly array $props,
        public readonly array $body,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'ComponentDeclStmt'; }
}

class ImportStmt extends StmtNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly array $names,
        public readonly string $source,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'ImportStmt'; }
}

class ExportStmt extends StmtNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly string $name,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'ExportStmt'; }
}

class TryCatchStmt extends StmtNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly array $tryBody,
        public readonly ?string $catchVar,
        public readonly ?array $catchBody,
        public readonly ?array $finallyBody,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'TryCatchStmt'; }
}

class ThrowStmt extends StmtNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly ExprNode $expression,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'ThrowStmt'; }
}

class CommentStmt extends StmtNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly string $text,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'CommentStmt'; }
}

class ClassDeclStmt extends StmtNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly string $name,
        public readonly ?string $extends,
        public readonly array $implements,
        public readonly array $body,
        public readonly array $modifiers = [],
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'ClassDeclStmt'; }
}

class AgentDeclStmt extends StmtNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly string $name,
        public readonly ?string $model,
        public readonly ?string $systemPrompt,
        public readonly array $tools,
        public readonly array $body,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'AgentDeclStmt'; }
}

class PromptStmt extends StmtNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly string $content,
        public readonly array $variables = [],
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'PromptStmt'; }
}

class EmbedStmt extends StmtNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly string $content,
        public readonly string $lang,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'EmbedStmt'; }
}

class ExpressionStmt extends StmtNode
{
    public function __construct(
        int $line,
        int $column,
        public readonly ExprNode $expression,
    ) {
        parent::__construct($line, $column);
    }

    public function getType(): string { return 'ExpressionStmt'; }
}

class ModuleNode implements Node
{
    public function __construct(
        public readonly array $statements,
        public readonly int $line = 1,
        public readonly int $column = 1,
    ) {}

    public function getLine(): int { return $this->line; }
    public function getColumn(): int { return $this->column; }
    public function getType(): string { return 'ModuleNode'; }
}
