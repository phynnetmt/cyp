<?php

namespace Cypher\RuntimeEngine\Bytecode;

class BytecodeCompiler
{
    private array $bytecode = [];
    private array $constants = [];
    private array $functions = [];
    private int $ip = 0;

    public function compile(array $ast): BytecodeProgram
    {
        $this->bytecode = [];
        $this->constants = [];
        $this->functions = [];
        $this->ip = 0;

        foreach ($ast as $node) {
            $this->compileNode($node);
        }

        $this->emit(Opcode::HALT);

        return new BytecodeProgram(
            bytecode: $this->bytecode,
            constants: $this->constants,
            functions: $this->functions,
        );
    }

    private function compileNode(array $node): void
    {
        switch ($node['type'] ?? '') {
            case 'say':
                $this->compileExpression($node['expression']);
                $this->emit(Opcode::SAY, 0);
                break;

            case 'var_decl':
                $this->compileExpression($node['value']);
                $this->emit(Opcode::DECLARE_VAR, $this->addConstant($node['name']));
                $this->emit(Opcode::STORE, $this->addConstant($node['name']));
                break;

            case 'assign':
                $this->compileExpression($node['value']);
                $this->emit(Opcode::STORE, $this->addConstant($node['name']));
                break;

            case 'if':
                $this->compileExpression($node['condition']);
                $elseJmp = $this->emit(Opcode::JMP_IF_FALSE, 0);
                foreach ($node['then_body'] ?? [] as $stmt) {
                    $this->compileNode($stmt);
                }
                $endJmp = $this->emit(Opcode::JMP, 0);
                $this->patchJump($elseJmp);
                foreach ($node['else_body'] ?? [] as $stmt) {
                    $this->compileNode($stmt);
                }
                $this->patchJump($endJmp);
                break;

            case 'while':
                $loopStart = $this->ip;
                $this->compileExpression($node['condition']);
                $exitJmp = $this->emit(Opcode::JMP_IF_FALSE, 0);
                foreach ($node['body'] ?? [] as $stmt) {
                    $this->compileNode($stmt);
                }
                $this->emit(Opcode::JMP, $loopStart);
                $this->patchJump($exitJmp);
                break;

            case 'for':
                $this->compileExpression($node['iterable']);
                $this->emit(Opcode::DECLARE_VAR, $this->addConstant($node['variable']));
                $loopStart = $this->ip;
                $exitJmp = $this->emit(Opcode::JMP_IF_FALSE, 0);
                foreach ($node['body'] ?? [] as $stmt) {
                    $this->compileNode($stmt);
                }
                $this->emit(Opcode::JMP, $loopStart);
                $this->patchJump($exitJmp);
                break;

            case 'task':
            case 'func':
                $funcName = $this->addConstant($node['name']);
                $funcStart = $this->ip;
                $this->emit(Opcode::DEF_FUNC, $funcName);
                foreach ($node['body'] ?? [] as $stmt) {
                    $this->compileNode($stmt);
                }
                $this->emit(Opcode::RETURN);
                $this->functions[] = [
                    'name' => $node['name'],
                    'start' => $funcStart,
                    'params' => $node['params'] ?? [],
                ];
                break;

            case 'return':
                if (isset($node['value'])) {
                    $this->compileExpression($node['value']);
                } else {
                    $this->emit(Opcode::PUSH_NULL);
                }
                $this->emit(Opcode::RETURN);
                break;

            case 'agent':
                $this->emit(Opcode::AGENT_RUN, $this->addConstant($node['name']));
                break;
        }
    }

    private function compileExpression(array $expr): void
    {
        switch ($expr['type'] ?? '') {
            case 'literal':
                $value = $expr['value'];
                if (is_null($value)) $this->emit(Opcode::PUSH_NULL);
                elseif (is_bool($value)) $this->emit($value ? Opcode::PUSH_TRUE : Opcode::PUSH_FALSE);
                elseif (is_int($value)) $this->emit(Opcode::PUSH_INT, $value);
                elseif (is_float($value)) $this->emit(Opcode::PUSH_FLOAT, $value);
                else $this->emit(Opcode::PUSH_STRING, $this->addConstant($value));
                break;

            case 'identifier':
                $this->emit(Opcode::LOAD, $this->addConstant($expr['name']));
                break;

            case 'binary':
                $this->compileExpression($expr['left']);
                $this->compileExpression($expr['right']);
                $op = match ($expr['operator']) {
                    '+' => Opcode::ADD, '-' => Opcode::SUB,
                    '*' => Opcode::MUL, '/' => Opcode::DIV,
                    '%' => Opcode::MOD,
                    '==' => Opcode::EQ, '!=' => Opcode::NEQ,
                    '<' => Opcode::LT, '>' => Opcode::GT,
                    '<=' => Opcode::LTE, '>=' => Opcode::GTE,
                    '&&', 'and' => Opcode::AND,
                    '||', 'or' => Opcode::OR,
                    default => Opcode::ADD,
                };
                $this->emit($op);
                break;

            case 'unary':
                $this->compileExpression($expr['operand']);
                if ($expr['operator'] === '-' || $expr['operator'] === '!') {
                    $this->emit(Opcode::NEG); // simplified
                }
                break;

            case 'call':
                foreach ($expr['arguments'] ?? [] as $arg) {
                    $this->compileExpression($arg);
                }
                $this->emit(Opcode::CALL, $this->addConstant($expr['callee']));
                break;
        }
    }

    private function emit(Opcode $opcode, int $operand = 0): int
    {
        $index = $this->ip;
        $this->bytecode[] = ['op' => $opcode, 'operand' => $operand];
        $this->ip++;
        return $index;
    }

    private function patchJump(int $index): void
    {
        $this->bytecode[$index]['operand'] = $this->ip;
    }

    private function addConstant(mixed $value): int
    {
        $this->constants[] = $value;
        return count($this->constants) - 1;
    }
}
