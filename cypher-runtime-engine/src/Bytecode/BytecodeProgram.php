<?php

namespace Cypher\RuntimeEngine\Bytecode;

class BytecodeProgram
{
    public function __construct(
        public readonly array $bytecode,
        public readonly array $constants,
        public readonly array $functions = [],
    ) {}

    public function disassemble(): string
    {
        $output = "=== Bytecode Program ===\n";
        foreach ($this->functions as $func) {
            $output .= "  Function: {$func['name']} (start: {$func['start']})\n";
        }
        $output .= "\n";

        foreach ($this->bytecode as $i => $instr) {
            $op = $instr['op'];
            $operand = $instr['operand'];
            $output .= sprintf("  %4d: %-20s", $i, $op->name);

            if ($op->operands() > 0) {
                $const = $this->constants[$operand] ?? $operand;
                $output .= " " . (is_string($const) ? "'{$const}'" : $const);
            }
            $output .= "\n";
        }

        return $output;
    }

    public function toArray(): array
    {
        return [
            'bytecode' => array_map(fn($i) => [
                'op' => $i['op']->value,
                'operand' => $i['operand'],
            ], $this->bytecode),
            'constants' => $this->constants,
            'functions' => $this->functions,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            bytecode: array_map(fn($i) => [
                'op' => Opcode::from($i['op']),
                'operand' => $i['operand'],
            ], $data['bytecode'] ?? []),
            constants: $data['constants'] ?? [],
            functions: $data['functions'] ?? [],
        );
    }
}
