<?php

namespace Cypher\Compiler\Semantic;

use Cypher\Compiler\AST\{
    ModuleNode, Node, StmtNode, ExprNode,
    VarDeclStmt, AssignStmt, SayStmt, IfStmt, WhileStmt,
    RepeatStmt, ForStmt, ReturnStmt, TaskDeclStmt, FuncDeclStmt,
    ModelDeclStmt, PageDeclStmt, ApiDeclStmt, ComponentDeclStmt,
    ImportStmt, ExportStmt, TryCatchStmt, ThrowStmt, ClassDeclStmt,
    AgentDeclStmt, ExpressionStmt,
    LiteralExpr, IdentifierExpr, BinaryExpr, UnaryExpr, CallExpr,
    PropertyAccessExpr, IndexExpr, ArrayExpr, RecordExpr, FieldExpr,
    MatchExpr, MatchArm, LambdaExpr, EmbedExpr, TernaryExpr,
    InterpolatedStringExpr,
};

class SemanticAnalyzer
{
    private array $scopes = [];
    private array $functions = [];
    private array $models = [];
    private array $pages = [];
    private array $apis = [];
    private array $classes = [];
    private array $errors = [];
    private ?string $currentFunctionReturnType = null;
    private bool $insideFunction = false;
    private bool $insideApi = false;

    public function analyze(ModuleNode $module): array
    {
        $this->enterScope();

        foreach ($module->statements as $stmt) {
            $this->analyzeStatement($stmt);
        }

        $this->exitScope();
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    private function analyzeStatement(StmtNode $stmt): void
    {
        match ($stmt::class) {
            VarDeclStmt::class => $this->analyzeVarDecl($stmt),
            AssignStmt::class => $this->analyzeAssign($stmt),
            SayStmt::class => $this->analyzeSay($stmt),
            IfStmt::class => $this->analyzeIf($stmt),
            WhileStmt::class => $this->analyzeWhile($stmt),
            RepeatStmt::class => $this->analyzeRepeat($stmt),
            ForStmt::class => $this->analyzeFor($stmt),
            ReturnStmt::class => $this->analyzeReturn($stmt),
            TaskDeclStmt::class => $this->analyzeTask($stmt),
            FuncDeclStmt::class => $this->analyzeFunction($stmt),
            ModelDeclStmt::class => $this->analyzeModel($stmt),
            PageDeclStmt::class => $this->analyzePage($stmt),
            ApiDeclStmt::class => $this->analyzeApi($stmt),
            ComponentDeclStmt::class => $this->analyzeComponent($stmt),
            ImportStmt::class => $this->analyzeImport($stmt),
            ExportStmt::class => $this->analyzeExport($stmt),
            TryCatchStmt::class => $this->analyzeTryCatch($stmt),
            ThrowStmt::class => $this->analyzeThrow($stmt),
            ClassDeclStmt::class => $this->analyzeClass($stmt),
            AgentDeclStmt::class => $this->analyzeAgent($stmt),
            ExpressionStmt::class => $this->analyzeExpressionStmt($stmt),
            default => null,
        };
    }

    private function analyzeVarDecl(VarDeclStmt $stmt): void
    {
        if ($this->isDeclaredInCurrentScope($stmt->name)) {
            $this->error($stmt, "Variable '{$stmt->name}' is already declared in this scope");
        }
        $this->analyzeExpression($stmt->initializer);
        $this->declareVariable($stmt->name, $stmt->typeHint, !$stmt->isMutable);
    }

    private function analyzeAssign(AssignStmt $stmt): void
    {
        $this->analyzeExpression($stmt->target);
        $this->analyzeExpression($stmt->value);

        if ($stmt->target instanceof IdentifierExpr) {
            $varInfo = $this->resolveVariable($stmt->target->name);
            if ($varInfo === null) {
                $this->error($stmt, "Variable '{$stmt->target->name}' is not defined");
            } elseif ($varInfo['readonly']) {
                $this->error($stmt, "Cannot assign to immutable variable '{$stmt->target->name}'");
            }
        }
    }

    private function analyzeSay(SayStmt $stmt): void
    {
        $this->analyzeExpression($stmt->expression);
    }

    private function analyzeIf(IfStmt $stmt): void
    {
        $this->analyzeExpression($stmt->condition);
        $this->enterScope();
        foreach ($stmt->thenBody as $s) $this->analyzeStatement($s);
        $this->exitScope();

        if ($stmt->elseIf) $this->analyzeStatement($stmt->elseIf);
        if ($stmt->elseBody) {
            $this->enterScope();
            foreach ($stmt->elseBody as $s) $this->analyzeStatement($s);
            $this->exitScope();
        }
    }

    private function analyzeWhile(WhileStmt $stmt): void
    {
        $this->analyzeExpression($stmt->condition);
        $this->enterScope();
        foreach ($stmt->body as $s) $this->analyzeStatement($s);
        $this->exitScope();
    }

    private function analyzeRepeat(RepeatStmt $stmt): void
    {
        $this->analyzeExpression($stmt->count);
        $this->enterScope();
        foreach ($stmt->body as $s) $this->analyzeStatement($s);
        $this->exitScope();
    }

    private function analyzeFor(ForStmt $stmt): void
    {
        $this->analyzeExpression($stmt->iterable);
        $this->enterScope();
        $this->declareVariable($stmt->variable, null, false);
        foreach ($stmt->body as $s) $this->analyzeStatement($s);
        $this->exitScope();
    }

    private function analyzeReturn(ReturnStmt $stmt): void
    {
        if ($stmt->value) {
            $this->analyzeExpression($stmt->value);
        }
        if (!$this->insideFunction && !$this->insideApi) {
            $this->error($stmt, "Return statement outside of a function");
        }
    }

    private function analyzeTask(TaskDeclStmt $stmt): void
    {
        if (isset($this->functions[$stmt->name])) {
            $this->error($stmt, "Function '{$stmt->name}' is already defined");
        }

        $this->functions[$stmt->name] = [
            'params' => $stmt->params,
            'returnType' => $stmt->returnType,
        ];

        $prevReturnType = $this->currentFunctionReturnType;
        $prevInside = $this->insideFunction;
        $this->currentFunctionReturnType = $stmt->returnType;
        $this->insideFunction = true;

        $this->enterScope();
        foreach ($stmt->params as $param) {
            $this->declareVariable($param->name, $param->typeHint, true);
        }
        foreach ($stmt->body as $s) $this->analyzeStatement($s);
        $this->exitScope();

        $this->currentFunctionReturnType = $prevReturnType;
        $this->insideFunction = $prevInside;
    }

    private function analyzeFunction(FuncDeclStmt $stmt): void
    {
        if (isset($this->functions[$stmt->name])) {
            $this->error($stmt, "Function '{$stmt->name}' is already defined");
        }

        $this->functions[$stmt->name] = [
            'params' => $stmt->params,
            'returnType' => $stmt->returnType,
        ];

        $prevReturnType = $this->currentFunctionReturnType;
        $prevInside = $this->insideFunction;
        $this->currentFunctionReturnType = $stmt->returnType;
        $this->insideFunction = true;

        $this->enterScope();
        foreach ($stmt->params as $param) {
            $this->declareVariable($param->name, $param->typeHint, true);
        }
        foreach ($stmt->body as $s) $this->analyzeStatement($s);
        $this->exitScope();

        $this->currentFunctionReturnType = $prevReturnType;
        $this->insideFunction = $prevInside;
    }

    private function analyzeModel(ModelDeclStmt $stmt): void
    {
        if (isset($this->models[$stmt->name])) {
            $this->error($stmt, "Model '{$stmt->name}' is already defined");
        }
        $this->models[$stmt->name] = $stmt;
    }

    private function analyzePage(PageDeclStmt $stmt): void
    {
        $this->pages[$stmt->name] = $stmt;
        $this->enterScope();
        foreach ($stmt->body as $s) $this->analyzeStatement($s);
        $this->exitScope();
    }

    private function analyzeApi(ApiDeclStmt $stmt): void
    {
        $key = $stmt->method . ':' . $stmt->path;
        if (isset($this->apis[$key])) {
            $this->error($stmt, "API route '{$stmt->method} {$stmt->path}' is already defined");
        }
        $this->apis[$key] = $stmt;
        $prevInside = $this->insideApi;
        $this->insideApi = true;
        $this->enterScope();
        foreach ($stmt->body as $s) $this->analyzeStatement($s);
        $this->exitScope();
        $this->insideApi = $prevInside;
    }

    private function analyzeComponent(ComponentDeclStmt $stmt): void
    {
        $this->enterScope();
        foreach ($stmt->props as $prop) {
            $this->declareVariable($prop->name, $prop->typeHint, true);
        }
        foreach ($stmt->body as $s) $this->analyzeStatement($s);
        $this->exitScope();
    }

    private function analyzeImport(ImportStmt $stmt): void {}
    private function analyzeExport(ExportStmt $stmt): void {}

    private function analyzeTryCatch(TryCatchStmt $stmt): void
    {
        $this->enterScope();
        foreach ($stmt->tryBody as $s) $this->analyzeStatement($s);
        $this->exitScope();

        if ($stmt->catchVar) {
            $this->enterScope();
            $this->declareVariable($stmt->catchVar, null, true);
            foreach ($stmt->catchBody as $s) $this->analyzeStatement($s);
            $this->exitScope();
        }

        if ($stmt->finallyBody) {
            $this->enterScope();
            foreach ($stmt->finallyBody as $s) $this->analyzeStatement($s);
            $this->exitScope();
        }
    }

    private function analyzeThrow(ThrowStmt $stmt): void
    {
        $this->analyzeExpression($stmt->expression);
    }

    private function analyzeClass(ClassDeclStmt $stmt): void
    {
        if (isset($this->classes[$stmt->name])) {
            $this->error($stmt, "Class '{$stmt->name}' is already defined");
        }
        $this->classes[$stmt->name] = $stmt;
        $this->enterScope();
        foreach ($stmt->body as $s) $this->analyzeStatement($s);
        $this->exitScope();
    }

    private function analyzeAgent(AgentDeclStmt $stmt): void
    {
        $this->enterScope();
        foreach ($stmt->body as $s) $this->analyzeStatement($s);
        $this->exitScope();
    }

    private function analyzeExpressionStmt(ExpressionStmt $stmt): void
    {
        $this->analyzeExpression($stmt->expression);
    }

    private function analyzeExpression(ExprNode $expr): string
    {
        return match ($expr::class) {
            LiteralExpr::class => $this->analyzeLiteral($expr),
            IdentifierExpr::class => $this->analyzeIdentifier($expr),
            BinaryExpr::class => $this->analyzeBinary($expr),
            UnaryExpr::class => $this->analyzeUnary($expr),
            CallExpr::class => $this->analyzeCall($expr),
            PropertyAccessExpr::class => $this->analyzePropertyAccess($expr),
            IndexExpr::class => $this->analyzeIndex($expr),
            ArrayExpr::class => $this->analyzeArray($expr),
            RecordExpr::class => $this->analyzeRecord($expr),
            MatchExpr::class => $this->analyzeMatch($expr),
            TernaryExpr::class => $this->analyzeTernary($expr),
            InterpolatedStringExpr::class => $this->analyzeInterpolatedString($expr),
            default => 'unknown',
        };
    }

    private function analyzeLiteral(LiteralExpr $expr): string
    {
        return $expr->literalType;
    }

    private function analyzeIdentifier(IdentifierExpr $expr): string
    {
        $var = $this->resolveVariable($expr->name);
        if ($var === null) {
            if (!isset($this->functions[$expr->name]) && !isset($this->models[$expr->name])) {
                $this->error($expr, "Undefined variable '{$expr->name}'");
            }
            return 'unknown';
        }
        return $var['type'] ?? 'unknown';
    }

    private function analyzeBinary(BinaryExpr $expr): string
    {
        $left = $this->analyzeExpression($expr->left);
        $right = $this->analyzeExpression($expr->right);
        return match ($expr->operator) {
            '+', '-', '*', '/', '%' => 'number',
            '==', '!=', '<', '>', '<=', '>=', '&&', '||', 'and', 'or' => 'bool',
            '.' => 'string',
            default => 'unknown',
        };
    }

    private function analyzeUnary(UnaryExpr $expr): string
    {
        $this->analyzeExpression($expr->operand);
        return match ($expr->operator) {
            '-', '~' => 'number',
            '!', 'not' => 'bool',
            default => 'unknown',
        };
    }

    private function analyzeCall(CallExpr $expr): string
    {
        foreach ($expr->arguments as $arg) {
            $this->analyzeExpression($arg);
        }

        if (isset($this->functions[$expr->callee])) {
            return $this->functions[$expr->callee]['returnType'] ?? 'unknown';
        }

        if (isset($this->models[$expr->callee])) {
            return $expr->callee;
        }

        return 'unknown';
    }

    private function analyzePropertyAccess(PropertyAccessExpr $expr): string
    {
        $this->analyzeExpression($expr->object);
        return 'unknown';
    }

    private function analyzeIndex(IndexExpr $expr): string
    {
        $this->analyzeExpression($expr->target);
        $this->analyzeExpression($expr->index);
        return 'unknown';
    }

    private function analyzeArray(ArrayExpr $expr): string
    {
        foreach ($expr->elements as $el) $this->analyzeExpression($el);
        return 'array';
    }

    private function analyzeRecord(RecordExpr $expr): string
    {
        foreach ($expr->fields as $field) $this->analyzeExpression($field->value);
        return 'record';
    }

    private function analyzeMatch(MatchExpr $expr): string
    {
        $this->analyzeExpression($expr->subject);
        foreach ($expr->arms as $arm) {
            $this->analyzeExpression($arm->pattern);
            $this->analyzeExpression($arm->value);
        }
        return 'unknown';
    }

    private function analyzeTernary(TernaryExpr $expr): string
    {
        $this->analyzeExpression($expr->condition);
        $t = $this->analyzeExpression($expr->thenExpr);
        $e = $this->analyzeExpression($expr->elseExpr);
        return $t === $e ? $t : 'unknown';
    }

    private function analyzeInterpolatedString(InterpolatedStringExpr $expr): string
    {
        foreach ($expr->parts as $part) {
            if ($part->isExpr) {
                $this->analyzeExpression($part->expression);
            }
        }
        return 'string';
    }

    private function enterScope(): void
    {
        $this->scopes[] = [];
    }

    private function exitScope(): void
    {
        array_pop($this->scopes);
    }

    private function declareVariable(string $name, ?string $type, bool $readonly): void
    {
        if (empty($this->scopes)) {
            $this->scopes[] = [];
        }
        $this->scopes[count($this->scopes) - 1][$name] = [
            'type' => $type,
            'readonly' => $readonly,
        ];
    }

    private function isDeclaredInCurrentScope(string $name): bool
    {
        if (empty($this->scopes)) return false;
        return isset($this->scopes[count($this->scopes) - 1][$name]);
    }

    private function resolveVariable(string $name): ?array
    {
        for ($i = count($this->scopes) - 1; $i >= 0; $i--) {
            if (isset($this->scopes[$i][$name])) {
                return $this->scopes[$i][$name];
            }
        }
        return null;
    }

    private function error(Node $node, string $message): void
    {
        $this->errors[] = [
            'line' => $node->getLine(),
            'column' => $node->getColumn(),
            'message' => $message,
        ];
    }
}
