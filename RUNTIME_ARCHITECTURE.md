# CYP Runtime Architecture v0.1.0

## Overview

The CYP runtime provides the execution environment for compiled CYP programs. It has three execution modes: AST Interpreter (bootstrap), Bytecode VM (production), and Full-Stack (application generation).

## Execution Modes

```
┌────────────────────────────────────────────────────────────┐
│                     CYP Runtime                             │
│                                                             │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────┐ │
│  │  AST Interpreter │  │  Bytecode VM     │  │ Full-Stack│ │
│  │  (Bootstrap)     │  │  (Production)    │  │ CodeGen  │ │
│  │                  │  │                  │  │          │ │
│  │  Walks AST nodes │  │  Stack-based     │  │ Laravel  │ │
│  │  directly        │  │  virtual machine │  │ React    │ │
│  │  No compilation  │  │  Sandboxed exec  │  │ Postgres │ │
│  └──────────────────┘  └──────────────────┘  └──────────┘ │
└────────────────────────────────────────────────────────────┘
```

## 1. AST Interpreter

### Purpose
The AST Interpreter is the bootstrap execution engine. It walks the Abstract Syntax Tree directly and executes each node without generating intermediate code.

### Design

```
┌──────────────────────────────────────────┐
│           AST Interpreter                │
│                                          │
│  ┌──────────┐                            │
│  │  Scope   │  Environment stack          │
│  │  Manager │  • Variable storage         │
│  └──────────┘  • Function definitions     │
│                • Block scoping            │
│  ┌──────────┐                            │
│  │  Visitor │  Node visitor pattern       │
│  │  Pattern │  • visitSayStmt()           │
│  └──────────┘  • visitVarDecl()           │
│                • visitIfStmt()            │
│                • visitForStmt()           │
│                • visitTaskDecl()          │
│                • visitExpression()        │
│  ┌──────────┐                            │
│  │  Output  │  Collects output buffer     │
│  │  Buffer  │  • Interpolated strings     │
│  └──────────┘  • say() output            │
└──────────────────────────────────────────┘
```

### Supported Operations

| Operation | Implementation |
|-----------|---------------|
| Variable declaration | Store in current scope |
| Variable assignment | Update in scope chain |
| Variable lookup | Walk scope chain |
| String output (`say`) | Append to output buffer |
| String interpolation | Evaluate `{expr}` segments |
| If/elif/else | Evaluate condition, execute branch |
| For loops | Iterate over array, execute body |
| Repeat loops | Repeat count times |
| While loops | Evaluate condition, loop |
| Function calls | Lookup function, bind args, execute |
| Return | Pop scope, return value |
| Binary operations | Evaluate LHS/RHS, apply operator |
| Match expressions | Compare subject to patterns |
| Array literals | Build array from element expressions |
| Record literals | Build record from field expressions |
| Try/catch | Execute, catch exceptions |
| Throw | Raise runtime exception |

## 2. Bytecode Virtual Machine

### Purpose
The Bytecode VM provides production-grade execution with sandboxing, concurrency, and performance monitoring.

### Architecture

```
┌──────────────────────────────────────────────────┐
│              VirtualMachine                       │
│                                                   │
│  ┌────────────────┐  ┌────────────────────────┐  │
│  │    Stack       │  │    Memory Manager      │  │
│  │  • Operand     │  │  • Allocation tracking │  │
│  │  • Call frames │  │  • Garbage collection  │  │
│  │  • Scopes      │  │  • Memory limits       │  │
│  └────────────────┘  └────────────────────────┘  │
│                                                   │
│  ┌────────────────┐  ┌────────────────────────┐  │
│  │  Instruction   │  │   Coroutine Scheduler  │  │
│  │  Pointer (IP)  │  │  • Cooperative multi-  │  │
│  │  • Bytecode    │  │    tasking             │  │
│  │  • Constants   │  │  • Async/await         │  │
│  │  • Functions   │  │  • Worker pool         │  │
│  └────────────────┘  └────────────────────────┘  │
│                                                   │
│  ┌────────────────┐  ┌────────────────────────┐  │
│  │  Security      │  │   Profiler             │  │
│  │  Sandbox       │  │  • Step counting       │  │
│  │  • Package     │  │  • Execution time      │  │
│  │    validation  │  │  • Memory usage        │  │
│  │  • System call │  │  • Call graph          │  │
│  │    filtering   │  └────────────────────────┘  │
│  └────────────────┘                               │
└──────────────────────────────────────────────────┘
```

### Opcode Set

| Category | Opcodes |
|----------|---------|
| Stack | `NOP`, `PUSH_NULL`, `PUSH_TRUE`, `PUSH_FALSE`, `PUSH_INT`, `PUSH_FLOAT`, `PUSH_STRING`, `PUSH_VAR`, `POP`, `DUP` |
| Variables | `STORE`, `LOAD`, `LOAD_CONST`, `DECLARE_VAR` |
| Arithmetic | `ADD`, `SUB`, `MUL`, `DIV`, `MOD`, `NEG` |
| Comparison | `EQ`, `NEQ`, `LT`, `GT`, `LTE`, `GTE` |
| Logical | `AND`, `OR`, `NOT` |
| Control | `JMP`, `JMP_IF_TRUE`, `JMP_IF_FALSE`, `CALL`, `CALL_NATIVE`, `RETURN`, `YIELD` |
| Data | `NEW_ARRAY`, `NEW_OBJECT`, `ARRAY_GET`, `ARRAY_SET`, `PROP_GET`, `PROP_SET` |
| Functions | `DEF_FUNC`, `DEF_ASYNC`, `AWAIT`, `SPAWN` |
| I/O | `PRINT`, `SAY`, `READ` |
| Agent | `AGENT_RUN`, `AGENT_SPAWN`, `MEMORY_STORE`, `MEMORY_SEARCH` |
| Debug | `HALT` |

### Execution Cycle

```
while (running && ip < program.length) {
    instruction = bytecode[ip]
    switch (instruction.opcode) {
        // Execute operation
    }
    ip++
}
```

### Bytecode Format

```json
{
  "bytecode": [
    { "op": "PUSH_STRING", "operand": 0 },
    { "op": "SAY", "operand": 0 },
    { "op": "HALT", "operand": 0 }
  ],
  "constants": ["Hello, World!"],
  "functions": [
    { "name": "main", "start": 0, "params": [] }
  ]
}
```

## 3. Memory Management

### Stack-based Allocation
- Operand stack for expression evaluation
- Call stack for function call/return
- Scope chain for variable lookup

### Garbage Collection (`MemoryManager`, `GarbageCollector`)
- Reference counting for simple cases
- Mark-and-sweep for cyclic references (planned)
- Memory limits per execution context

## 4. Concurrency

### Coroutine Scheduler (`CoroutineScheduler`)
- Cooperative multitasking
- Async/await support
- Worker pool for parallel execution
- Non-blocking I/O

## 5. Security Sandbox

### Features
- Execution step limit (prevents infinite loops)
- Memory usage limits
- System call filtering
- Package validation
- Safe native function interface

## 6. AI Runtime

### Components
- **Agent Engine**: Task execution, tool calling
- **Memory Systems**: Short-term, long-term, episodic, semantic, vector
- **Reasoning Strategies**: Direct, Chain-of-Thought, Tree-of-Thought
- **Multi-Agent**: Team orchestration, message passing
- **Workflow Engine**: Step-based workflow execution

## 7. Error Handling

```
Runtime errors:
    - Undefined variable
    - Type mismatch
    - Division by zero
    - Index out of bounds
    - Function not found
    - Stack overflow
    - Memory limit exceeded
    - Execution timeout
```

## 8. Integration with Compiler

```
Compiler Pipeline:
    Source → Lexer → Parser → Semantic → TypeCheck
                                    │
                                    ▼
                        ┌──────────────────────┐
                        │  Execution Decision   │
                        ├──────────────────────┤
                        │  say/var/if/for? → AST │
                        │  Full app? → CodeGen  │
                        │  Bytecode? → VM       │
                        └──────────────────────┘
```

The runtime selects the appropriate execution mode based on the program structure:
- Simple scripts with `say`, variables, control flow → AST Interpreter
- Complex programs with models, pages, APIs → Full-Stack Code Generator
- Bytecode compilation target → Bytecode VM
