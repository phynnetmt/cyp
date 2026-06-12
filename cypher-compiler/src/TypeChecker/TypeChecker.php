<?php

namespace Cypher\Compiler\TypeChecker;

use Cypher\Compiler\AST\{
    ModuleNode, Node, StmtNode, ExprNode,
    VarDeclStmt, AssignStmt, SayStmt, IfStmt, WhileStmt,
    RepeatStmt, ForStmt, ReturnStmt, TaskDeclStmt, FuncDeclStmt,
    ModelDeclStmt, PageDeclStmt, ApiDeclStmt, ComponentDeclStmt,
    ImportStmt, ExportStmt, TryCatchStmt, ThrowStmt, ClassDeclStmt,
    AgentDeclStmt, ExpressionStmt, ParamDecl,
    LiteralExpr, IdentifierExpr, BinaryExpr, UnaryExpr, CallExpr,
    PropertyAccessExpr, IndexExpr, ArrayExpr, RecordExpr, FieldExpr,
    MatchExpr, MatchArm, LambdaExpr, EmbedExpr, TernaryExpr,
    InterpolatedStringExpr,
};

class TypeChecker
{
    private array $errors = [];
    private array $symbolTable = [];

    public function check(ModuleNode $module): array
    {
        foreach ($module->statements as $stmt) {
            $this->checkStatement($stmt);
        }
        return $this->errors;
    }

    public function hasErrors(): bool { return !empty($this->errors); }

    private function checkStatement(StmtNode $stmt): ?string
    {
        return match ($stmt::class) {
            VarDeclStmt::class => $this->checkVarDecl($stmt),
            AssignStmt::class => $this->checkAssign($stmt),
            SayStmt::class => $this->checkSay($stmt),
            ReturnStmt::class => $this->checkReturn($stmt),
            TaskDeclStmt::class => $this->checkTask($stmt),
            FuncDeclStmt::class => $this->checkFunc($stmt),
            IfStmt::class => $this->checkIf($stmt),
            WhileStmt::class => $this->checkWhile($stmt),
            RepeatStmt::class => $this->checkRepeat($stmt),
            ForStmt::class => $this->checkFor($stmt),
            ModelDeclStmt::class => null,
            PageDeclStmt::class => $this->checkPage($stmt),
            ApiDeclStmt::class => $this->checkApi($stmt),
            ComponentDeclStmt::class => null,
            ImportStmt::class => null,
            ExportStmt::class => null,
            TryCatchStmt::class => $this->checkTryCatch($stmt),
            ThrowStmt::class => $this->checkThrow($stmt),
            ClassDeclStmt::class => null,
            AgentDeclStmt::class => null,
            ExpressionStmt::class => $this->checkExpr($stmt->expression),
            default => null,
        };
    }

    private function checkIf(IfStmt $stmt): ?string
    {
        $this->checkExpr($stmt->condition);
        foreach ($stmt->thenBody as $s) $this->checkStatement($s);
        if ($stmt->elseIf) $this->checkStatement($stmt->elseIf);
        if ($stmt->elseBody) foreach ($stmt->elseBody as $s) $this->checkStatement($s);
        return null;
    }

    private function checkWhile(WhileStmt $stmt): ?string
    {
        $this->checkExpr($stmt->condition);
        foreach ($stmt->body as $s) $this->checkStatement($s);
        return null;
    }

    private function checkRepeat(RepeatStmt $stmt): ?string
    {
        $this->checkExpr($stmt->count);
        foreach ($stmt->body as $s) $this->checkStatement($s);
        return null;
    }

    private function checkFor(ForStmt $stmt): ?string
    {
        $this->checkExpr($stmt->iterable);
        $this->symbolTable[$stmt->variable] = 'mixed';
        foreach ($stmt->body as $s) $this->checkStatement($s);
        return null;
    }

    private function checkPage(PageDeclStmt $stmt): ?string
    {
        foreach ($stmt->body as $s) $this->checkStatement($s);
        return null;
    }

    private function checkApi(ApiDeclStmt $stmt): ?string
    {
        foreach ($stmt->body as $s) $this->checkStatement($s);
        return null;
    }

    private function checkTryCatch(TryCatchStmt $stmt): ?string
    {
        foreach ($stmt->tryBody as $s) $this->checkStatement($s);
        if ($stmt->catchVar) {
            $this->symbolTable[$stmt->catchVar] = 'string';
            foreach ($stmt->catchBody as $s) $this->checkStatement($s);
        }
        if ($stmt->finallyBody) foreach ($stmt->finallyBody as $s) $this->checkStatement($s);
        return null;
    }

    private function checkThrow(ThrowStmt $stmt): ?string
    {
        return $this->checkExpr($stmt->expression);
    }

    private function checkVarDecl(VarDeclStmt $stmt): ?string
    {
        $valueType = $this->checkExpr($stmt->initializer);
        if ($stmt->typeHint && $valueType !== null && !$this->isCompatible($stmt->typeHint, $valueType)) {
            $this->error($stmt, "Type mismatch: cannot assign {$valueType} to variable '{$stmt->name}' of type {$stmt->typeHint}");
        }
        $this->symbolTable[$stmt->name] = $stmt->typeHint ?? $valueType ?? 'mixed';
        return $this->symbolTable[$stmt->name];
    }

    private function checkAssign(AssignStmt $stmt): ?string
    {
        $valueType = $this->checkExpr($stmt->value);
        if ($stmt->target instanceof IdentifierExpr) {
            $this->symbolTable[$stmt->target->name] = $valueType ?? 'mixed';
        }
        return $valueType;
    }

    private function checkSay(SayStmt $stmt): ?string
    {
        return $this->checkExpr($stmt->expression);
    }

    private function checkReturn(ReturnStmt $stmt): ?string
    {
        return $stmt->value ? $this->checkExpr($stmt->value) : 'void';
    }

    private function checkTask(TaskDeclStmt $stmt): ?string
    {
        foreach ($stmt->params as $p) {
            $this->symbolTable[$p->name] = $p->typeHint ?? 'mixed';
        }
        foreach ($stmt->body as $s) $this->checkStatement($s);
        return $stmt->returnType ?? 'void';
    }

    private function checkFunc(FuncDeclStmt $stmt): ?string
    {
        foreach ($stmt->params as $p) {
            $this->symbolTable[$p->name] = $p->typeHint ?? 'mixed';
        }
        foreach ($stmt->body as $s) $this->checkStatement($s);
        return $stmt->returnType ?? 'mixed';
    }

    private function checkExpr(ExprNode $expr): ?string
    {
        return match ($expr::class) {
            LiteralExpr::class => match ($expr->literalType) {
                'string' => 'string',
                'int', 'float' => 'number',
                'bool' => 'boolean',
                'null' => 'null',
                default => 'mixed',
            },
            IdentifierExpr::class => $this->symbolTable[$expr->name] ?? 'mixed',
            BinaryExpr::class => $this->checkBinary($expr),
            UnaryExpr::class => $this->checkExpr($expr->operand),
            CallExpr::class => 'mixed',
            PropertyAccessExpr::class => 'mixed',
            IndexExpr::class => 'mixed',
            ArrayExpr::class => 'array',
            RecordExpr::class => 'object',
            MatchExpr::class => 'mixed',
            TernaryExpr::class => $this->checkTernary($expr),
            InterpolatedStringExpr::class => 'string',
            default => 'mixed',
        };
    }

    private function checkBinary(BinaryExpr $expr): ?string
    {
        $left = $this->checkExpr($expr->left);
        $right = $this->checkExpr($expr->right);

        return match ($expr->operator) {
            '+', '-', '*', '/', '%' => 'number',
            '==', '!=', '<', '>', '<=', '>=', '&&', '||', 'and', 'or' => 'boolean',
            '.' => 'string',
            default => 'mixed',
        };
    }

    private function checkTernary(TernaryExpr $expr): ?string
    {
        $t = $this->checkExpr($expr->thenExpr);
        $e = $this->checkExpr($expr->elseExpr);
        return ($t === $e) ? $t : 'mixed';
    }

    private function isCompatible(string $expected, string $actual): bool
    {
        $compat = [
            'int' => ['number', 'int', 'float'],
            'number' => ['number', 'int', 'float'],
            'float' => ['number', 'float'],
            'string' => ['string'],
            'bool' => ['bool', 'boolean'],
            'boolean' => ['bool', 'boolean'],
            'mixed' => ['string', 'number', 'int', 'float', 'bool', 'boolean', 'array', 'object', 'null', 'mixed'],
            'array' => ['array', 'null'],
            'object' => ['object', 'null'],
            'null' => ['null'],
        ];
        return isset($compat[strtolower($expected)]) && in_array(strtolower($actual), $compat[strtolower($expected)], true);
    }

    private function error(Node $node, string $message): void
    {
        $this->errors[] = ['line' => $node->getLine(), 'column' => $node->getColumn(), 'message' => $message];
    }
}
