# CYP Compiler Architecture v0.1.0

## Overview

The CYP compiler transforms `.cyp` source files into executable output. It is designed as a modular pipeline where each phase is independent and replaceable — enabling the eventual self-hosting transition where each phase is rewritten in CYP.

## Pipeline Architecture

```
Source (.cyp)
    │
    ▼
┌─────────────────────────────────────────────────────────┐
│                    Source Loader                         │
│  • Reads .cyp files from disk                           │
│  • Resolves imports and module paths                     │
│  • Manages project structure (pages/, models/, etc.)     │
│  • Handles encoding validation                          │
└───────────────────────┬─────────────────────────────────┘
                        │ Source text
                        ▼
┌─────────────────────────────────────────────────────────┐
│                      Lexer                               │
│  • Tokenizes source into Token stream                    │
│  • Recognizes keywords, identifiers, literals, operators │
│  • Tracks line/column positions for error reporting      │
│  • Skips comments (# ...)                                │
│  • Produces: array<Token>                                │
└───────────────────────┬─────────────────────────────────┘
                        │ Token stream
                        ▼
┌─────────────────────────────────────────────────────────┐
│                      Parser                              │
│  • Recursive descent parser                              │
│  • Builds Abstract Syntax Tree (AST)                     │
│  • Handles operator precedence and associativity         │
│  • Reports syntax errors with context                    │
│  • Produces: ModuleNode (root of AST)                    │
└───────────────────────┬─────────────────────────────────┘
                        │ AST
                        ▼
┌─────────────────────────────────────────────────────────┐
│                 Semantic Analyzer                        │
│  • Scope resolution and symbol table construction        │
│  • Variable declaration validation                       │
│  • Function signature validation                         │
│  • Duplicate declaration detection                       │
│  • Undefined reference detection                         │
│  • Reports semantic errors with location context         │
│  • Produces: Analyzed AST                                │
└───────────────────────┬─────────────────────────────────┘
                        │ Analyzed AST
                        ▼
┌─────────────────────────────────────────────────────────┐
│                   Type Checker                           │
│  • Static type validation                                │
│  • Type inference for variables                          │
│  • Type compatibility checking in assignments            │
│  • Function return type verification                     │
│  • Reports type errors                                   │
│  • Produces: Type-checked AST                            │
└───────────────────────┬─────────────────────────────────┘
                        │ Type-checked AST
                        ▼
┌─────────────────────────────────────────────────────────┐
│                     Optimizer                            │
│  • Constant folding                                      │
│  • Dead code elimination (future)                        │
│  • Expression simplification (future)                    │
│  • Inlining (future)                                     │
│  • Produces: Optimized AST                               │
└───────────────────────┬─────────────────────────────────┘
                        │ Optimized AST
                        ▼
┌─────────────────────────────────────────────────────────┐
│                   Code Generator                         │
│  ┌─────────────────────────────────────────────┐        │
│  │           Generation Modes:                  │        │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐    │        │
│  │  │  AST     │ │ Bytecode │ │  Target  │    │        │
│  │  │Interpreter│ │  VM      │ │  CodeGen │    │        │
│  │  └──────────┘ └──────────┘ └──────────┘    │        │
│  └─────────────────────────────────────────────┘        │
└───────────────────────┬─────────────────────────────────┘
                        │ Generated output
                        ▼
              ┌─────────────────────┐
              │    Build Output     │
              │  • PHP / Laravel    │
              │  • React / TypeScript│
              │  • PostgreSQL SQL   │
              │  • Docker / CI/CD   │
              │  • Bytecode (.cyb)  │
              └─────────────────────┘
```

## Phase Details

### 1. Source Loader (`SourceLoader/`)

**Files:** `SourceLoader.php`, `LoadedSource.php`

Responsibilities:
- Load `.cyp` source files from disk
- Support multi-file project loading (all `.cyp` files in conventional directories)
- Resolve module imports with search paths
- Cache loaded modules to prevent redundant I/O
- Support project-level discovery (via `AppProject`)

### 2. Lexer (`Lexer/`)

**Files:** `Lexer.php`, `Token.php`, `TokenType.php`

Responsibilities:
- Character-by-character scanning
- Recognize all token types defined in `TokenType` enum
- Handle string interpolation (`{expr}` inside strings)
- Track line and column numbers for error reporting
- Skip comments and whitespace

Key design: Single-pass, no backtracking. Emits tokens sequentially.

### 3. Parser (`Parser/`)

**Files:** `Parser.php`

Responsibilities:
- Recursive descent parsing with one token lookahead
- Build concrete syntax tree → abstract syntax tree
- Handle all statement types (var decl, if, for, tasks, models, pages, APIs, agents, etc.)
- Proper operator precedence via cascading parse functions
- Meaningful error messages with location context

Parse functions hierarchy:
```
parseProgram()
  parseStatement()
    parseVarDecl()
    parseAssignment()
    parseSay()
    parseIf()
    parseFor()
    parseRepeat()
    parseWhile()
    parseTask/Func()
    parseModel()
    parsePage()
    parseApi()
    parseComponent()
    parseClass()
    parseAgent()
    parseTryCatch()
    parseMatch()
    parseExpression()
```

### 4. Semantic Analyzer (`Semantic/`)

**Files:** `SemanticAnalyzer.php`

Responsibilities:
- First pass: collect all declarations (variables, functions, models, pages, etc.)
- Build scope tree and symbol table
- Second pass: resolve references and validate usage
- Detect: undefined variables, duplicate declarations, wrong arity in calls
- Produce annotated AST or error list

### 5. Type Checker (`TypeChecker/`)

**Files:** `TypeChecker.php`

Responsibilities:
- Validate type compatibility in assignments
- Check function argument types against parameter types
- Infer types where possible
- Report type mismatches with clear messages

Supported types:
- Primitive: `int`, `float`, `string`, `bool`, `null`
- Composite: `array`, `record`, `function`
- Void (for functions with no return)

### 6. Optimizer (`Optimizer/`)

**Files:** `Optimizer.php`

Responsibilities:
- Constant folding: evaluate constant expressions at compile time
- Dead code elimination (planned)
- Expression simplification (planned)

### 7. Code Generation

Three modes:

#### 7.1 AST Interpreter (Bootstrap Mode)
Executes the AST directly without generating intermediate code. Used for:
- Running simple `.cyp` scripts directly
- Development and debugging
- Bootstrapping the runtime

#### 7.2 Bytecode VM (`RuntimeEngine/`)
Compiles AST to bytecode for the stack-based VirtualMachine:
- Platform-independent instruction set
- Sandboxed execution
- Suitable for production deployment

#### 7.3 Full-Stack CodeGen (`CodeGen/`)
Generates complete application code:
- **LaravelGenerator**: PHP backend (models, controllers, migrations)
- **ReactGenerator**: TypeScript frontend (pages, components, API client)
- **PostgresGenerator**: SQL schema and migrations
- **AuthGenerator**: Authentication scaffolding
- **DeploymentGenerator**: Docker and CI/CD configuration

## Self-Hosting Architecture

The compiler is designed for incremental self-hosting:

### Generation 0 (Current)
- Written in PHP
- Full compiler pipeline: Lexer → Parser → Semantic → TypeCheck → CodeGen
- Target: Any (PHP, React, SQL, Bytecode)

### Generation 1
- Write Lexer in CYP
- Bootstrap: PHP compiler reads CYP lexer source, compiles it, replaces PHP lexer
- Milestone: "CYP lexer compiles itself"

### Generation 2
- Write Parser in CYP
- Bootstrap: PHP compiler reads CYP parser source, replaces PHP parser
- Milestone: "CYP parser compiles itself"

### Generation 3
- Write Semantic Analyzer in CYP
- Bootstrap same pattern
- Milestone: "CYP semantic analysis compiles itself"

### Generation 4
- Write Code Generator in CYP
- Bootstrap same pattern
- Milestone: "CYP compiler compiles itself"

### Generation 5
- Entire compiler rewritten in CYP
- PHP bootstrap layer removed
- Milestone: "Fully self-hosting"

## Data Flow

```
Source Code (string)
    → Token[] (Lexer)
    → ModuleNode (Parser)
    → ModuleNode with symbols (SemanticAnalyzer)
    → ModuleNode with types (TypeChecker)
    → ModuleNode optimized (Optimizer)
    → Generated files (CodeGen)
```

## Key Design Decisions

1. **No intermediate representation**: The AST is the canonical representation throughout the pipeline. This simplifies the bootstrap and makes the compiler easier to reason about.

2. **Newlines as statement terminators**: CYP uses newlines (not semicolons) to separate statements, making the language more readable.

3. **Implicit variable declaration**: Variables are declared on first assignment. This reduces boilerplate while maintaining type safety via inference.

4. **Modular code generators**: The generation phase is pluggable. Adding new output targets (WASM, mobile, etc.) doesn't require changing earlier pipeline phases.

5. **Project-level compilation**: The compiler treats a directory of `.cyp` files as a single compilation unit, aggregating all declarations before code generation.
