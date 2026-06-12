<?php

namespace Cypher\RuntimeEngine\Bytecode;

enum Opcode: int
{
    // Stack operations
    case NOP = 0;
    case PUSH_NULL = 1;
    case PUSH_TRUE = 2;
    case PUSH_FALSE = 3;
    case PUSH_INT = 4;
    case PUSH_FLOAT = 5;
    case PUSH_STRING = 6;
    case PUSH_VAR = 7;
    case POP = 8;
    case DUP = 9;

    // Variable operations
    case STORE = 10;
    case LOAD = 11;
    case LOAD_CONST = 12;
    case DECLARE_VAR = 13;

    // Arithmetic
    case ADD = 20;
    case SUB = 21;
    case MUL = 22;
    case DIV = 23;
    case MOD = 24;
    case NEG = 25;

    // Comparison
    case EQ = 30;
    case NEQ = 31;
    case LT = 32;
    case GT = 33;
    case LTE = 34;
    case GTE = 35;

    // Logical
    case AND = 40;
    case OR = 41;
    case NOT = 42;

    // Control flow
    case JMP = 50;
    case JMP_IF_TRUE = 51;
    case JMP_IF_FALSE = 52;
    case CALL = 53;
    case CALL_NATIVE = 54;
    case RETURN = 55;
    case YIELD = 56;

    // Objects / arrays
    case NEW_ARRAY = 60;
    case NEW_OBJECT = 61;
    case ARRAY_GET = 62;
    case ARRAY_SET = 63;
    case PROP_GET = 64;
    case PROP_SET = 65;

    // Functions
    case DEF_FUNC = 70;
    case DEF_ASYNC = 71;
    case AWAIT = 72;
    case SPAWN = 73;

    // I/O
    case PRINT = 80;
    case SAY = 81;
    case READ = 82;

    // Agent
    case AGENT_RUN = 90;
    case AGENT_SPAWN = 91;
    case MEMORY_STORE = 92;
    case MEMORY_SEARCH = 93;

    // Debug
    case HALT = 255;

    public function operands(): int
    {
        return match ($this) {
            self::PUSH_INT, self::PUSH_FLOAT, self::PUSH_STRING,
            self::PUSH_VAR, self::STORE, self::LOAD, self::LOAD_CONST,
            self::DECLARE_VAR, self::JMP, self::JMP_IF_TRUE, self::JMP_IF_FALSE,
            self::CALL, self::CALL_NATIVE, self::DEF_FUNC, self::DEF_ASYNC,
            self::SAY, self::PRINT => 1,
            self::ARRAY_GET, self::ARRAY_SET, self::PROP_GET, self::PROP_SET,
            self::AGENT_RUN, self::MEMORY_STORE, self::MEMORY_SEARCH => 1,
            default => 0,
        };
    }
}
