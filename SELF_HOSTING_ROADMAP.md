# CYP Self-Hosting Roadmap

## Vision

A fully self-hosting CYP ecosystem where every component is written in CYP itself:

```
Compiler = CYP
Runtime  = CYP
CLI      = CYP
Packages = CYP
Framework = CYP
Tools    = CYP
```

## Generations

### Generation 0: Bootstrap (Current — PHP)

**Status:** ✅ Complete
**Bootstrap language:** PHP 8.1+

**Architecture:**
```
.cyp → PHP Lexer → PHP Parser → PHP SemAnal → PHP TypeCheck → PHP CodeGen → Output
```

**Key files:**
- `cypher-compiler/` — Full compiler pipeline in PHP
- `cypher-runtime-engine/` — Bytecode VM in PHP
- `cypher-runtime/` — AI runtime in PHP
- `cypher-cli/` — CLI tools in PHP

**Self-hosting readiness:**
- ✅ Modular pipeline (each phase is a separate class)
- ✅ AST-based architecture (no intermediate language dependency)
- ✅ Bytecode VM exists (foundation for self-hosted runtime)
- ✅ Type system and semantic analysis exist
- ❌ No CYP-written compiler components yet

**Verification:**
```
echo 'say "Hello, World!"' > hello.cyp
php cypc run hello.cyp
# Output: Hello, World!
```

### Generation 1: Lexer in CYP

**Goal:** Replace the PHP lexer with a CYP-written lexer.

**Approach:**
1. Write `lexer.cyp` that tokenizes CYP source code
2. Use PHP bootstrap compiler to compile `lexer.cyp` → PHP lexer
3. Replace PHP Lexer with compiled output
4. Verify: compiler still works, now using a CYP-written lexer

**Implementation:**
```cypher
# lexer.cyp — CYP Lexer written in CYP
task tokenize(source)
    tokens = []
    pos = 0
    while pos < len(source)
        char = source[pos]
        if char == '#'
            # skip comment
            while pos < len(source) and source[pos] != '\n'
                pos = pos + 1
            end
        elif isLetter(char)
            # read identifier/keyword
            start = pos
            while pos < len(source) and isAlphanumeric(source[pos])
                pos = pos + 1
            end
            tokens = append(tokens, {type: "identifier", value: substr(source, start, pos)})
        # ... more token types
        end
    end
    return tokens
end
```

**Success criteria:**
```
php cypc run lexer.cyp --test
# Compiler uses CYP-generated lexer
cypc run hello.cyp
# Output: Hello, World!
```

**Timeline:** Phase 2 milestone

### Generation 2: Parser in CYP

**Goal:** Replace the PHP parser with a CYP-written parser.

**Approach:**
1. Write `parser.cyp` that builds AST from token stream
2. Compile with bootstrap compiler
3. Replace PHP Parser with compiled output
4. Verify full pipeline still works

**Implementation:**
```cypher
# parser.cyp — CYP Parser written in CYP
task parseProgram(tokens)
    statements = []
    pos = 0
    while pos < len(tokens)
        stmt = parseStatement(tokens, pos)
        statements = append(statements, stmt.node)
        pos = stmt.nextPos
    end
    return {type: "program", statements: statements}
end
```

**Success criteria:**
```
php cypc run parser.cyp --test
# Compiler uses CYP-generated parser
cypc build app.cyp
# Full application generated successfully
```

### Generation 3: Semantic Analyzer in CYP

**Goal:** Replace the PHP semantic analyzer.

**Approach:**
1. Write `semantic.cyp` — scope checking, variable resolution, function validation
2. Compile, replace, verify

### Generation 4: Type Checker in CYP

**Goal:** Replace the PHP type checker.

### Generation 5: AST Interpreter in CYP

**Goal:** Replace the PHP AST interpreter.

### Generation 6: Bytecode Compiler in CYP

**Goal:** Replace the PHP bytecode compiler.

### Generation 7: Virtual Machine in CYP

**Goal:** Replace the PHP VM.

### Generation 8: Full Code Generator in CYP

**Goal:** Replace all PHP code generators.

### Generation 9: CLI in CYP

**Goal:** The `cyp` command itself is a CYP program.

## Self-Hosting Milestones

| Milestone | Description | Components Replaced |
|-----------|-------------|-------------------|
| **M0** | Bootstrap compiler works | None (all PHP) |
| **M1** | Lexer self-hosts | Lexer |
| **M2** | Parser self-hosts | Lexer, Parser |
| **M3** | Semantic analyzer self-hosts | Lexer, Parser, Semantic |
| **M4** | Type checker self-hosts | Lexer, Parser, Semantic, TypeCheck |
| **M5** | AST interpreter self-hosts | Lexer, Parser, Semantic, TypeCheck, AstInterpreter |
| **M6** | Bytecode compiler self-hosts | All compiler phases |
| **M7** | VM self-hosts | Compiler + Runtime |
| **M8** | CLI self-hosts | Everything |
| **M9** | Bootstrap removed | PHP dependency eliminated |

## Dependency Graph

```
M0: PHP bootstrap
 │
 M1: CYP Lexer ────────────── replaces PHP Lexer
 │
 M2: CYP Parser ───────────── replaces PHP Parser
 │
 M3: CYP Semantic ─────────── replaces PHP Semantic
 │
 M4: CYP TypeChecker ──────── replaces PHP TypeChecker
 │
 M5: CYP AST Interpreter ──── replaces PHP AST Interpreter
 │
 M6: CYP BytecodeCompiler ─── replaces PHP BytecodeCompiler
 │
 M7: CYP VM ───────────────── replaces PHP VM
 │
 M8: CYP CLI ──────────────── replaces PHP CLI
 │
 M9: Remove PHP bootstrap
 ```

## Verification Strategy

Each generation follows the same verification pattern:

```
1. Write component in CYP (.cyp)
2. Compile with current bootstrap compiler
3. Replace PHP component with compiled output
4. Run full test suite
5. If tests pass, generation is complete
6. If tests fail, fix CYP component
```

### Test Suite

The test suite must be comprehensive enough to verify correctness at each stage:

```
Tests:
  ├── Lexer tests (tokenization, errors)
  ├── Parser tests (AST construction, errors)
  ├── Semantic tests (scope, resolution, errors)
  ├── Type tests (type checking, inference)
  ├── CodeGen tests (output correctness)
  ├── Runtime tests (execution, errors)
  ├── Integration tests (full pipeline)
  └── Regression tests (fixed bugs)
```

## Cross-Cutting Concerns

### Error Handling
Each component must maintain error reporting quality as it transitions to CYP:
- Error messages must include source location (line:column)
- Error messages must be human-readable
- The error format must remain stable across generations

### Performance
The bootstrap phase may be slower than PHP. Performance optimization is deferred until after M9.

### Testing
The test suite must be executable from within CYP (M5+). This requires:
- A CYP test runner
- Assertion functions in CYP
- Test discovery and reporting

## Bootstrap Removal

After M9, the PHP bootstrap directory is removed:

```
Removed:
  cypher-compiler/    (entire directory — all phases replaced)
  cypher-runtime/     (entire directory — all phases replaced)
  cypher-runtime-engine/ (entire directory — all phases replaced)
  cypher-cli/         (entire directory — all phases replaced)
  composer.json       (no longer needed)
  vendor/             (no longer needed)

Remaining:
  *.cyp files         (source of truth)
  .cyp/               (compiled CYP)
  cyp                 (single binary — self-hosted compiler)
```

## Risk Mitigation

| Risk | Mitigation |
|------|-----------|
| Circular dependency during bootstrap | Always compile with previous generation; never require current generation to compile itself |
| Performance regression | Accept during bootstrap; optimize after self-hosting |
| Bug propagation | Comprehensive test suite at each generation |
| Feature gaps in CYP | Extend CYP language before attempting component migration |
| Loss of PHP expertise | Document all architecture decisions; keep reference implementation |
