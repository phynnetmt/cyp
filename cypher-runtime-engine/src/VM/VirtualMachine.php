<?php

namespace Cypher\RuntimeEngine\VM;

use Cypher\RuntimeEngine\Bytecode\BytecodeProgram;
use Cypher\RuntimeEngine\Bytecode\Opcode;
use Cypher\RuntimeEngine\Memory\MemoryManager;
use Cypher\RuntimeEngine\Concurrency\CoroutineScheduler;

class VirtualMachine
{
    private array $stack = [];
    private array $variables = [];
    private array $functions = [];
    private int $ip = 0;
    private BytecodeProgram $program;
    private MemoryManager $memory;
    private ?CoroutineScheduler $scheduler = null;
    private array $callStack = [];
    private bool $running = false;
    public array $output = [];

    public function __construct(?MemoryManager $memory = null)
    {
        $this->memory = $memory ?? new MemoryManager();
    }

    public function load(BytecodeProgram $program): void
    {
        $this->program = $program;
        $this->functions = [];
        foreach ($program->functions as $func) {
            $this->functions[$func['name']] = $func;
        }
    }

    public function setScheduler(CoroutineScheduler $scheduler): void
    {
        $this->scheduler = $scheduler;
    }

    public function execute(?string $entryPoint = null): VMResult
    {
        $this->stack = [];
        $this->variables = [];
        $this->ip = 0;
        $this->callStack = [];
        $this->running = true;
        $this->output = [];
        $startTime = microtime(true);

        if ($entryPoint && isset($this->functions[$entryPoint])) {
            $this->ip = $this->functions[$entryPoint]['start'];
        }

        $steps = 0;
        $maxSteps = 100000;

        try {
            while ($this->running && $this->ip < count($this->program->bytecode) && $steps < $maxSteps) {
                $instr = $this->program->bytecode[$this->ip];
                $this->executeInstruction($instr);
                $this->ip++;
                $steps++;
            }

            $duration = (microtime(true) - $startTime) * 1000;

            return new VMResult(
                success: true,
                output: implode("\n", $this->output),
                steps: $steps,
                durationMs: $duration,
                stack: $this->stack,
            );
        } catch (VMException $e) {
            return new VMResult(
                success: false,
                output: implode("\n", $this->output),
                error: $e->getMessage(),
                steps: $steps,
                stack: $this->stack,
            );
        }
    }

    private function executeInstruction(array $instr): void
    {
        $op = $instr['op'];
        $operand = $instr['operand'];

        switch ($op) {
            case Opcode::NOP: break;

            case Opcode::PUSH_NULL: $this->stack[] = null; break;
            case Opcode::PUSH_TRUE: $this->stack[] = true; break;
            case Opcode::PUSH_FALSE: $this->stack[] = false; break;
            case Opcode::PUSH_INT: $this->stack[] = $operand; break;
            case Opcode::PUSH_FLOAT: $this->stack[] = (float)$operand; break;
            case Opcode::PUSH_STRING: $this->stack[] = $this->program->constants[$operand] ?? ''; break;
            case Opcode::PUSH_VAR: $this->stack[] = $this->variables[$operand] ?? null; break;

            case Opcode::POP: array_pop($this->stack); break;
            case Opcode::DUP:
                $top = end($this->stack);
                $this->stack[] = $top;
                break;

            case Opcode::STORE:
                $value = array_pop($this->stack);
                $name = $this->program->constants[$operand] ?? $operand;
                $this->variables[$name] = $value;
                break;

            case Opcode::LOAD:
                $name = $this->program->constants[$operand] ?? $operand;
                $this->stack[] = $this->variables[$name] ?? null;
                break;

            case Opcode::DECLARE_VAR:
                $name = $this->program->constants[$operand] ?? $operand;
                if (!isset($this->variables[$name])) {
                    $this->variables[$name] = null;
                }
                break;

            // Arithmetic
            case Opcode::ADD: $this->binaryOp(fn($a, $b) => $a + $b); break;
            case Opcode::SUB: $this->binaryOp(fn($a, $b) => $a - $b); break;
            case Opcode::MUL: $this->binaryOp(fn($a, $b) => $a * $b); break;
            case Opcode::DIV: $this->binaryOp(fn($a, $b) => $b != 0 ? $a / $b : null); break;
            case Opcode::MOD: $this->binaryOp(fn($a, $b) => $b != 0 ? $a % $b : null); break;

            // Comparison
            case Opcode::EQ: $this->binaryOp(fn($a, $b) => $a == $b); break;
            case Opcode::NEQ: $this->binaryOp(fn($a, $b) => $a != $b); break;
            case Opcode::LT: $this->binaryOp(fn($a, $b) => $a < $b); break;
            case Opcode::GT: $this->binaryOp(fn($a, $b) => $a > $b); break;
            case Opcode::LTE: $this->binaryOp(fn($a, $b) => $a <= $b); break;
            case Opcode::GTE: $this->binaryOp(fn($a, $b) => $a >= $b); break;

            // Logical
            case Opcode::AND: $this->binaryOp(fn($a, $b) => $a && $b); break;
            case Opcode::OR: $this->binaryOp(fn($a, $b) => $a || $b); break;
            case Opcode::NOT:
                $val = array_pop($this->stack);
                $this->stack[] = !$val;
                break;

            // Control flow
            case Opcode::JMP: $this->ip = $operand - 1; break;
            case Opcode::JMP_IF_TRUE:
                $cond = array_pop($this->stack);
                if ($cond) $this->ip = $operand - 1;
                break;
            case Opcode::JMP_IF_FALSE:
                $cond = array_pop($this->stack);
                if (!$cond) $this->ip = $operand - 1;
                break;

            case Opcode::CALL:
                $funcName = $this->program->constants[$operand] ?? '';
                if (isset($this->functions[$funcName])) {
                    $this->callStack[] = $this->ip;
                    $this->ip = $this->functions[$funcName]['start'];
                } else {
                    $this->output[] = "Call to undefined function: {$funcName}";
                }
                break;

            case Opcode::CALL_NATIVE:
                $handler = $this->program->constants[$operand] ?? '';
                $this->output[] = "[native: {$handler}]";
                break;

            case Opcode::RETURN:
                if (!empty($this->callStack)) {
                    $this->ip = array_pop($this->callStack);
                } else {
                    $this->running = false;
                }
                break;

            // I/O
            case Opcode::SAY:
            case Opcode::PRINT:
                $value = array_pop($this->stack);
                $this->output[] = (string)($value ?? '');
                break;

            case Opcode::HALT: $this->running = false; break;

            default:
                throw new VMException("Unknown opcode: {$op->name}");
        }
    }

    private function binaryOp(callable $op): void
    {
        $b = array_pop($this->stack);
        $a = array_pop($this->stack);
        $this->stack[] = $op($a, $b);
    }
}
