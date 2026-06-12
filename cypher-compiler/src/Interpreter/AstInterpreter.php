<?php

namespace Cypher\Compiler\Interpreter;

use Cypher\Compiler\AST\{
    ModuleNode, Node, ExprNode, StmtNode,
    VarDeclStmt, AssignStmt, SayStmt, IfStmt, WhileStmt,
    RepeatStmt, ForStmt, ReturnStmt, TaskDeclStmt, FuncDeclStmt,
    ExpressionStmt, ImportStmt, ExportStmt, TryCatchStmt, ThrowStmt,
    LiteralExpr, IdentifierExpr, BinaryExpr, UnaryExpr, CallExpr,
    PropertyAccessExpr, IndexExpr, ArrayExpr, RecordExpr, FieldExpr,
    MatchExpr, MatchArm, LambdaExpr, EmbedExpr, TernaryExpr,
    InterpolatedStringExpr, StringPart,
};

use Cypher\Compiler\Interpreter\InterpreterException;

class AstInterpreter
{
    private array $variables = [];
    private array $functions = [];
    private array $output = [];
    private mixed $returnValue = null;
    private bool $hasReturned = false;

    private int $maxSteps = 100000;
    private int $stepCount = 0;

    public function interpret(ModuleNode $ast): InterpreterResult
    {
        $this->reset();

        try {
            $this->executeStatements($ast->statements);
            return new InterpreterResult(
                success: true,
                output: implode("\n", $this->output),
            );
        } catch (InterpreterException $e) {
            return new InterpreterResult(
                success: false,
                output: implode("\n", $this->output),
                error: $e->getMessage(),
            );
        }
    }

    private function reset(): void
    {
        $this->variables = [];
        $this->functions = [];
        $this->output = [];
        $this->pendingError = null;
        $this->stepCount = 0;
    }

    private function executeStatements(array $statements): void
    {
        foreach ($statements as $stmt) {
            if ($this->hasReturned) break;
            $this->executeStatement($stmt);
        }
    }

    private function executeStatement(StmtNode $stmt): mixed
    {
        $this->checkSteps();

        return match ($stmt::class) {
            VarDeclStmt::class => $this->executeVarDecl($stmt),
            AssignStmt::class => $this->executeAssign($stmt),
            SayStmt::class => $this->executeSay($stmt),
            IfStmt::class => $this->executeIf($stmt),
            WhileStmt::class => $this->executeWhile($stmt),
            RepeatStmt::class => $this->executeRepeat($stmt),
            ForStmt::class => $this->executeFor($stmt),
            ReturnStmt::class => $this->executeReturn($stmt),
            TaskDeclStmt::class => $this->executeTaskDecl($stmt),
            FuncDeclStmt::class => $this->executeFuncDecl($stmt),
            ExpressionStmt::class => $this->evaluateExpression($stmt->expression),
            TryCatchStmt::class => $this->executeTryCatch($stmt),
            ThrowStmt::class => throw new InterpreterException("Caught: " . $this->evaluateExpression($stmt->expression)),
            default => null,
        };
    }

    private function executeVarDecl(VarDeclStmt $stmt): void
    {
        $this->variables[$stmt->name] = $this->evaluateExpression($stmt->initializer);
    }

    private function executeAssign(AssignStmt $stmt): void
    {
        $value = $this->evaluateExpression($stmt->value);

        if ($stmt->target instanceof IdentifierExpr) {
            $this->variables[$stmt->target->name] = $value;
        } elseif ($stmt->target instanceof PropertyAccessExpr) {
            $obj = $this->evaluateExpression($stmt->target->object);
            if (is_array($obj)) {
                $obj[$stmt->target->property] = $value;
            }
        } elseif ($stmt->target instanceof IndexExpr) {
            $target = $this->evaluateExpression($stmt->target->target);
            $index = $this->evaluateExpression($stmt->target->index);
            if (is_array($target) && is_int($index)) {
                $target[$index] = $value;
            }
        }
    }

    private function executeSay(SayStmt $stmt): void
    {
        $value = $this->evaluateExpression($stmt->expression);
        $this->output[] = $this->formatValue($value);
    }

    private function executeIf(IfStmt $stmt): void
    {
        $condition = $this->evaluateExpression($stmt->condition);
        if ($condition) {
            $this->executeStatements($stmt->thenBody);
        } elseif ($stmt->elseIf !== null) {
            $this->executeStatement($stmt->elseIf);
        } elseif ($stmt->elseBody !== null) {
            $this->executeStatements($stmt->elseBody);
        }
    }

    private function executeWhile(WhileStmt $stmt): void
    {
        while ($this->evaluateExpression($stmt->condition)) {
            $this->checkSteps();
            $this->executeStatements($stmt->body);
        }
    }

    private function executeRepeat(RepeatStmt $stmt): void
    {
        $count = $this->evaluateExpression($stmt->count);
        for ($i = 1; $i <= $count; $i++) {
            $this->checkSteps();
            $this->variables['i'] = $i;
            $this->executeStatements($stmt->body);
        }
    }

    private function executeFor(ForStmt $stmt): void
    {
        $iterable = $this->evaluateExpression($stmt->iterable);
        if (is_array($iterable)) {
            foreach ($iterable as $item) {
                $this->checkSteps();
                $this->variables[$stmt->variable] = $item;
                $this->executeStatements($stmt->body);
            }
        }
    }

    private function executeReturn(ReturnStmt $stmt): mixed
    {
        if ($stmt->value !== null) {
            $this->returnValue = $this->evaluateExpression($stmt->value);
        } else {
            $this->returnValue = null;
        }
        $this->hasReturned = true;
        return $this->returnValue;
    }

    private function executeTaskDecl(TaskDeclStmt $stmt): void
    {
        $this->functions[$stmt->name] = [
            'type' => 'task',
            'params' => $stmt->params,
            'body' => $stmt->body,
        ];
    }

    private function executeFuncDecl(FuncDeclStmt $stmt): void
    {
        $this->functions[$stmt->name] = [
            'type' => 'func',
            'params' => $stmt->params,
            'body' => $stmt->body,
        ];
    }

    private function executeTryCatch(TryCatchStmt $stmt): void
    {
        try {
            $this->executeStatements($stmt->tryBody);
        } catch (InterpreterException $e) {
            if ($stmt->catchVar !== null) {
                $this->variables[$stmt->catchVar] = $e->getMessage();
            }
            if ($stmt->catchBody !== null) {
                $this->executeStatements($stmt->catchBody);
            }
        } finally {
            if ($stmt->finallyBody !== null) {
                $this->executeStatements($stmt->finallyBody);
            }
        }
    }

    public function evaluateExpression(ExprNode $expr): mixed
    {
        $this->checkSteps();

        return match ($expr::class) {
            LiteralExpr::class => $this->interpolateString((string)$expr->value),
            IdentifierExpr::class => $this->variables[$expr->name] ?? null,
            BinaryExpr::class => $this->evaluateBinary($expr),
            UnaryExpr::class => $this->evaluateUnary($expr),
            CallExpr::class => $this->evaluateCall($expr),
            PropertyAccessExpr::class => $this->evaluatePropertyAccess($expr),
            IndexExpr::class => $this->evaluateIndex($expr),
            ArrayExpr::class => $this->evaluateArray($expr),
            RecordExpr::class => $this->evaluateRecord($expr),
            InterpolatedStringExpr::class => $this->evaluateInterpolatedString($expr),
            MatchExpr::class => $this->evaluateMatch($expr),
            TernaryExpr::class => $this->evaluateTernary($expr),
            default => null,
        };
    }

    private function evaluateBinary(BinaryExpr $expr): mixed
    {
        $left = $this->evaluateExpression($expr->left);
        $right = $this->evaluateExpression($expr->right);

        return match ($expr->operator) {
            '+' => $left + $right,
            '-' => $left - $right,
            '*' => $left * $right,
            '/' => $right != 0 ? $left / $right : null,
            '%' => $right != 0 ? $left % $right : null,
            '==' => $left == $right,
            '!=' => $left != $right,
            '<' => $left < $right,
            '>' => $left > $right,
            '<=' => $left <= $right,
            '>=' => $left >= $right,
            '&&', 'and' => $left && $right,
            '||', 'or' => $left || $right,
            default => null,
        };
    }

    private function evaluateUnary(UnaryExpr $expr): mixed
    {
        $operand = $this->evaluateExpression($expr->operand);

        return match ($expr->operator) {
            '-' => -$operand,
            '!', 'not' => !$operand,
            default => $operand,
        };
    }

    private function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            if ($this->isRecord($value)) {
                $parts = [];
                foreach ($value as $k => $v) {
                    $parts[] = "{$k}: " . $this->formatValue($v);
                }
                return '{' . implode(', ', $parts) . '}';
            }
            return '[' . implode(', ', array_map(fn($v) => $this->formatValue($v), $value)) . ']';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        return (string)$value;
    }

    private function isRecord(array $arr): bool
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    private function evaluateCall(CallExpr $expr): mixed
    {
        $callee = $expr->callee;
        $args = array_map(fn($a) => $this->evaluateExpression($a), $expr->arguments);

        if ($callee === 'say') {
            $this->output[] = $this->formatValue($args[0] ?? '');
            return null;
        }

        return $this->callUserFunction($callee, $args);
    }

    private function evaluatePropertyAccess(PropertyAccessExpr $expr): mixed
    {
        $object = $this->evaluateExpression($expr->object);
        if (is_array($object) && isset($object[$expr->property])) {
            return $object[$expr->property];
        }
        return null;
    }

    private function evaluateIndex(IndexExpr $expr): mixed
    {
        $target = $this->evaluateExpression($expr->target);
        $index = $this->evaluateExpression($expr->index);

        if (is_array($target) && is_int($index) && isset($target[$index])) {
            return $target[$index];
        }
        return null;
    }

    private function evaluateArray(ArrayExpr $expr): array
    {
        return array_map(fn($e) => $this->evaluateExpression($e), $expr->elements);
    }

    private function evaluateRecord(RecordExpr $expr): array
    {
        $record = [];
        foreach ($expr->fields as $field) {
            $record[$field->name] = $this->evaluateExpression($field->value);
        }
        return $record;
    }

    private function evaluateInterpolatedString(InterpolatedStringExpr $expr): string
    {
        $result = '';
        foreach ($expr->parts as $part) {
            if ($part->isExpr && $part->expression !== null) {
                $result .= (string)$this->evaluateExpression($part->expression);
            } else {
                $result .= $part->value;
            }
        }
        return $result;
    }

    private function evaluateMatch(MatchExpr $expr): mixed
    {
        $subject = $this->evaluateExpression($expr->subject);
        foreach ($expr->arms as $arm) {
            $pattern = $this->evaluateExpression($arm->pattern);
            if ($subject == $pattern) {
                return $this->evaluateExpression($arm->value);
            }
        }
        return null;
    }

    private function evaluateTernary(TernaryExpr $expr): mixed
    {
        $condition = $this->evaluateExpression($expr->condition);
        if ($condition) {
            return $this->evaluateExpression($expr->thenExpr);
        }
        return $this->evaluateExpression($expr->elseExpr);
    }

    private function interpolateString(string $value): string
    {
        if (!str_contains($value, '{')) {
            return $value;
        }

        return preg_replace_callback('/\{([^}]+)\}/', function ($matches) {
            $inner = trim($matches[1]);

            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $inner)) {
                $val = $this->variables[$inner] ?? null;
                if ($val !== null || array_key_exists($inner, $this->variables)) {
                    return $this->formatValue($val);
                }
                return $matches[0];
            }

            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*\(\s*(.*?)\s*\)$/', $inner, $m)) {
                $funcName = $m[1];
                $argsStr = $m[2];
                if (isset($this->functions[$funcName])) {
                    $args = [];
                    if (!empty($argsStr)) {
                        $argParts = explode(',', $argsStr);
                        foreach ($argParts as $part) {
                            $part = trim($part);
                            if (is_numeric($part)) {
                                $args[] = str_contains($part, '.') ? (float)$part : (int)$part;
                            } elseif (isset($this->variables[$part])) {
                                $args[] = $this->variables[$part];
                            } else {
                                $args[] = $part;
                            }
                        }
                    }
                    try {
                        $result = $this->callUserFunction($funcName, $args);
                        return $this->formatValue($result);
                    } catch (\Exception $e) {
                        return $matches[0];
                    }
                }
                return $matches[0];
            }

            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*([+\-*\/%])\s*([a-zA-Z0-9_.]+)$/', $inner, $m)) {
                $left = $this->variables[$m[1]] ?? (is_numeric($m[1]) ? (float)$m[1] : 0);
                $op = $m[2];
                $right = $this->variables[$m[3]] ?? (is_numeric($m[3]) ? (float)$m[3] : 0);
                $result = match ($op) {
                    '+' => $left + $right,
                    '-' => $left - $right,
                    '*' => $left * $right,
                    '/' => $right != 0 ? $left / $right : 'NaN',
                    '%' => $right != 0 ? $left % $right : 'NaN',
                    default => $matches[0],
                };
                return $this->formatValue($result);
            }

            return $matches[0];
        }, $value);
    }

    private function callUserFunction(string $name, array $args): mixed
    {
        if (!isset($this->functions[$name])) {
            throw new InterpreterException("Undefined function: {$name}");
        }

        $func = $this->functions[$name];
        $savedVars = $this->variables;
        $savedReturn = $this->returnValue;
        $savedHasReturned = $this->hasReturned;

        $this->variables = [];
        $this->returnValue = null;
        $this->hasReturned = false;

        foreach ($func['params'] as $i => $param) {
            $this->variables[$param->name] = $args[$i] ?? null;
        }

        foreach ($func['body'] as $stmt) {
            if ($this->hasReturned) break;
            $this->executeStatement($stmt);
        }

        $result = $this->returnValue;

        $this->variables = $savedVars;
        $this->returnValue = $savedReturn;
        $this->hasReturned = $savedHasReturned;

        return $result;
    }

    private function checkSteps(): void
    {
        $this->stepCount++;
        if ($this->stepCount > $this->maxSteps) {
            throw new InterpreterException("Execution step limit exceeded ({$this->maxSteps})");
        }
    }
}

class InterpreterException extends \RuntimeException {}
