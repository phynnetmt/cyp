<p align="center">
  <img src="https://img.shields.io/badge/version-0.1.0-blue.svg" alt="Version 0.1.0">
  <img src="https://img.shields.io/badge/license-CYP--1.0-green.svg" alt="License CYP-1.0">
  <img src="https://img.shields.io/badge/PHP-%3E%3D8.1-777BB4.svg" alt="PHP >=8.1">
  <img src="https://img.shields.io/badge/build-passing-brightgreen.svg" alt="Build Passing">
  <img src="https://img.shields.io/badge/AI--Native-Yes-ff69b4.svg" alt="AI-Native">
  <img src="https://img.shields.io/badge/PRs-welcome-orange.svg" alt="PRs Welcome">
</p>

<h1 align="center">CYP — The Cypher Programming Language</h1>
<h3 align="center">Build Anything. Deploy Anywhere. Scale Intelligently.</h3>

<p align="center">
  <strong>AI-Native · Full-Stack · Compiler-to-Code Platform</strong>
  <br>
  <i>An open-source programming language that compiles complete applications — frontend, backend, database, and AI agents — from a single, unified syntax.</i>
</p>

---

## Table of Contents

- [What is CYP?](#what-is-cyp)
- [Core Philosophy](#core-philosophy)
- [Key Features](#key-features)
- [Architecture Overview](#architecture-overview)
- [Quick Start](#quick-start)
- [Language Tour](#language-tour)
- [Ecosystem](#ecosystem)
- [Project Structure](#project-structure)
- [Roadmap](#roadmap)
- [Contributing](#contributing)
- [Security](#security)
- [License](#license)

---

## What is CYP?

**CYP (Cypher)** is an AI-native, full-stack programming language and application platform. It is the first language designed from the ground up to treat AI agents, databases, APIs, and user interfaces as first-class citizens of the language itself.

Unlike traditional languages that require stitching together multiple frameworks, CYP lets you describe your entire application — models, pages, APIs, agents, and workflows — in a single `.cyp` file. The compiler then generates production-ready code for each tier:

| Target | Technology |
|--------|-----------|
| **Frontend** | React + TypeScript + Tailwind CSS (via Vite) |
| **Backend** | PHP + Laravel 12 |
| **Database** | PostgreSQL (with PGVector) |
| **AI Agents** | Multi-agent frameworks with memory & reasoning |
| **Cloud** | Docker Compose + CI/CD pipelines |

### Why CYP Exists

Modern software development is fragmented. Building even a simple CRUD app requires mastering React, Laravel, PostgreSQL, Docker, and a dozen configuration files. CYP eliminates this complexity by providing a single, unified language that compiles to all tiers simultaneously.

### Problems CYP Solves

| Problem | CYP Solution |
|---------|-------------|
| Framework lock-in | Target-agnostic compilation — swap stacks with config |
| Context-switching between languages | One syntax for backend, frontend, DB, and AI |
| Boilerplate generation | Compiler generates migrations, controllers, API clients, auth |
| AI integration complexity | First-class `agent` and `task` primitives |
| Deployment fragmentation | Built-in Docker + CI/CD generation |

### Long-Term Vision

CYP aims to become the standard language for AI-augmented application development — where human developers describe intent and AI agents collaborate to build, test, and deploy software autonomously. The language is designed to evolve alongside foundation models, with agent, memory, and reasoning primitives built into the core specification.

---

## Core Philosophy

### Simplicity

CYP's syntax is minimal and expressive. A complete full-stack application with models, pages, APIs, and agents fits in under 50 lines:

```cypher
model User
    id: int
    name: string
    email: string unique
    password: string
end

page home
    say "<h1>Welcome to CYP</h1>"
end

api GET "/api/users"
    return User::all()
end
```

### AI Native

AI agents are not an afterthought — they are language primitives. CYP supports agent definitions, prompts, multi-agent teams, chain-of-thought reasoning, memory systems, and tool use at the language level.

### Full Stack

Write models, pages, components, APIs, and agents in one file. The compiler generates all tiers with proper routing, dependency injection, type safety, and database migrations.

### Developer Productivity

- Hot-reload development servers for all tiers
- Automatic authentication scaffolding (register, login, password reset)
- Built-in deployment generation (Docker, nginx, GitHub Actions, GitLab CI)
- Integrated package manager with dependency resolution

### Enterprise Readiness

- SOC 2, ISO 27001, GDPR, HIPAA, PCI-DSS, NIST, FedRAMP compliance modules
- Multi-tenancy, SSO, RBAC, MFA, audit trails
- Cost management and analytics platforms
- Certified enterprise agent clusters with private knowledge networks

---

## Key Features

| Feature | Description |
|---------|-------------|
| **Unified Language** | One syntax for backend, frontend, database, AI |
| **AI Agents** | First-class `agent` with memory, reasoning, tools, multi-agent teams |
| **Model ORM** | Declarative models with relationships, migrations, factories |
| **API Generation** | Route definitions compile to REST controllers |
| **Page Generation** | UI components compile to React + Tailwind |
| **Auth Scaffolding** | Full authentication flow (register, login, logout, password reset) |
| **Type Checking** | Static type checking across all tiers |
| **Embedded Code** | Inline PHP embedding for escape hatches |
| **Package Manager** | Dependency resolution, lock files, registry client |
| **Bytecode VM** | Stack-based virtual machine with sandboxed execution |
| **Deployment** | Automatic Docker, nginx, CI/CD generation |
| **Enterprise Suite** | Compliance, multi-tenancy, audit, identity, governance |

---

## Architecture Overview

### Compiler Pipeline

```
Source (.cyp)
    │
    ▼
┌─────────────────┐
│    Lexer        │  Tokenizes source into tokens
└────────┬────────┘
         ▼
┌─────────────────┐
│    Parser       │  Builds AST (recursive descent)
└────────┬────────┘
         ▼
┌───────────────────┐
│ Semantic Analyzer │  Scope resolution, symbol table
└────────┬──────────┘
         ▼
┌─────────────────┐
│  Type Checker   │  Static type validation
└────────┬────────┘
         ▼
┌─────────────────┐
│   Optimizer     │  Constant folding, dead code elimination
└────────┬────────┘
         ▼
┌──────────────────────────────┐
│       Code Generators        │
│  ┌────────┐ ┌───────────┐   │
│  │ Laravel│ │  React    │   │
│  │ Backend│ │  Frontend │   │
│  └───┬────┘ └─────┬─────┘   │
│  ┌────────┐ ┌───────────┐   │
│  │Postgres│ │Deployment │   │
│  └────────┘ └───────────┘   │
└────────┬─────────────────────┘
         ▼
    ┌──────────┐
    │  build/  │  ← Generated application
    └──────────┘
```

### Runtime Architecture

```
┌─────────────────────────────────────────┐
│           CYP Runtime Engine            │
├────────────────┬────────────────────────┤
│  Virtual       │  Concurrency           │
│  Machine       │  (Coroutines, Workers) │
├────────────────┼────────────────────────┤
│  Memory        │  Garbage Collector     │
│  Manager       │                        │
├────────────────┼────────────────────────┤
│  Security      │  Profiler &            │
│  Sandbox       │  Benchmark             │
├────────────────┴────────────────────────┤
│  AI Runtime                              │
│  ┌────────┐ ┌──────────┐ ┌──────────┐   │
│  │ Agent  │ │ Memory   │ │Reasoning │   │
│  │ Engine │ │ Systems  │ │Strategies│   │
│  └────────┘ └──────────┘ └──────────┘   │
│  ┌────────┐ ┌──────────┐                │
│  │ Tools  │ │Workflow  │                │
│  │Registry│ │ Engine   │                │
│  └────────┘ └──────────┘                │
└─────────────────────────────────────────┘
```

### Application Generation Flow

```
.cyp Source ──► Compiler ──► Laravel Backend
                              ├── Models + Migrations
                              ├── Controllers + Routes
                              ├── Form Requests + Resources
                              ├── Auth Scaffolding
                              └── Tests
                           ──► React Frontend
                              ├── Vite + TypeScript + Tailwind
                              ├── Page Components
                              ├── API Client (Axios)
                              ├── Auth Context
                              └── Routes
                           ──► PostgreSQL
                              ├── Schema + Migrations
                              ├── Indexes + Foreign Keys
                              └── PGVector Extensions
                           ──► Deployment
                              ├── Docker Compose
                              ├── Dockerfiles
                              ├── nginx Config
                              └── CI/CD Pipelines
```

---

## Quick Start

### Prerequisites

- PHP 8.1 or higher
- Composer
- Node.js 18+ (for frontend generation)

### Installation

```bash
# Clone the repository
git clone https://github.com/phynnetmt/cyp.git
cd cyp

# Install dependencies
composer install

# Verify the compiler
php cypher-compiler/bin/cypc --version
```

### Your First Application

Create a file `hello.cyp`:

```cypher
# Hello, CYP!
say "Hello, World!"

name = "Cypher"
say "Welcome to the {name} programming language"
```

Compile and run:

```bash
php cypher-compiler/bin/cypc build hello.cyp
```

### Generate a Full-Stack Application

Create `app.cyp`:

```cypher
model Product
    id: int
    name: string
    price: float
    description: text
end

page products
    title = "Our Products"
    items = Product::all()
    for item in items
        say "<div>{item.name} — ${item.price}</div>"
    end
end

api GET "/api/products"
    return Product::all()
end
```

Build the entire application:

```bash
php cypher-compiler/bin/cypc build app.cyp

# Generated output in build/:
#   build/backend/   → Laravel project
#   build/frontend/  → React + TypeScript project
#   build/database/  → SQL migrations
#   build/deploy/    → Docker + CI/CD
```

---

## Language Tour

### Variables

```cypher
name = "Cypher"          # string (inferred)
count = 42               # int (inferred)
price = 19.99            # float (inferred)
active = true            # bool (inferred)
```

### String Interpolation

```cypher
name = "Cypher"
version = "0.1.0"
say "Hello from {name} v{version}"   # Curly brace interpolation
say "Hello ${name}"                   # Dollar-brace interpolation
```

### Functions (Tasks)

```cypher
task add(a: int, b: int): int
    return a + b
end

result = add(5, 3)
say "5 + 3 = {result}"

# Lambda / arrow function
func double(x) => x * 2
```

### Conditionals

```cypher
if result > 5
    say "Greater than 5"
elif result == 5
    say "Exactly 5"
else
    say "5 or less"
end
```

### Loops

```cypher
# For loop
items = ["apple", "banana", "cherry"]
for item in items
    say "Fruit: {item}"
end

# Repeat loop
repeat 3
    say "Loop iteration"
end
```

### Match Expressions

```cypher
status = 200
result = match status
    200 => "OK"
    404 => "Not Found"
    500 => "Server Error"
end
```

### Models

```cypher
model User
    id: int
    name: string
    email: string unique
    password: string
    posts hasMany Post
end

model Post
    id: int
    title: string
    body: text
    user_id: int
    user belongsTo User
end
```

### Pages

```cypher
page dashboard
    title = "Admin Dashboard"
    items = ["Users", "Products", "Settings"]
    for item in items
        say "<div class='p-4'>{item}</div>"
    end
end
```

### API Routes

```cypher
api GET "/api/products"
    return Product::all()
end

api POST "/api/products"
    data = {name: "New Product", price: 29.99}
    return {status: "created", product: data}
end
```

### AI Agents

```cypher
agent Assistant: gpt4
    prompt "You are a helpful assistant"

    task answer(question)
        response = ask(question)
        return response
    end

    task summarize(text)
        # Chain-of-thought reasoning
        key_points = analyze(text)
        return summarize(key_points)
    end
end
```

### Embedded Code

```cypher
embed php "echo 'Hello from embedded PHP';"
```

### Try / Catch / Throw

```cypher
try
    result = riskyOperation()
    say "Result: {result}"
catch |e|
    say "Error: {e}"
finally
    cleanup()
end
```

### Records and Arrays

```cypher
# Array
fruits = ["apple", "banana", "cherry"]
first = fruits[0]

# Record (object literal)
product = {name: "Widget", price: 9.99, inStock: true}
```

### Imports and Exports

```cypher
import { parseUser } from "./utils"
import http from "cypher-std/http"

export task formatDate(date)
    return date.format("YYYY-MM-DD")
end
```

---

## Ecosystem

CYP is more than a language — it is a complete ecosystem of tools, services, and community programs.

### Core Platform

| Component | Description |
|-----------|-------------|
| **Cypher Compiler** (`cypc`) | Compiles `.cyp` to backend, frontend, database, and deployment artifacts |
| **Cypher Runtime** | AI agent framework with memory, reasoning, multi-agent systems, workflows |
| **Cypher Runtime Engine** | Bytecode VM, coroutine scheduler, memory manager, security sandbox |
| **Cypher Cloud** | Managed deployment, monitoring, billing, marketplace, agent cloud |
| **Cypher CLI** (`cyp`) | Project scaffolding, build commands, package management |
| **Cypher Registry** | Package registry with security auditing, dependency resolution, developer portal |

### Community & Governance

| Component | Description |
|-----------|-------------|
| **Cypher Foundation** | Open-source governance, board of directors, bylaws, policies |
| **Cypher Ecosystem** | Community platform, events, academy, university program, partner tiers |
| **Certification** | Official developer and implementation certification |
| **Standards** | Language specification, runtime spec, package format, security standards |

### Enterprise

| Component | Description |
|-----------|-------------|
| **Compliance Platform** | SOC 2, ISO 27001, GDPR, HIPAA, PCI-DSS, NIST, FedRAMP |
| **Enterprise Agents** | Departmental agents, private knowledge networks, isolated clusters |
| **Multi-Tenancy** | Tenant isolation, shared resource pooling, per-tenant configuration |
| **Identity Platform** | SSO, RBAC, MFA, directory integration |
| **Governance Platform** | Policy evaluation engine, compliance assessment, audit trails |

---

## Project Structure

```
cypher-language/
├── cypher-compiler/        # Compiler: lexer, parser, semantic analysis, code generation
│   ├── bin/                #  CLI entrypoints (cypc, cyp)
│   ├── src/
│   │   ├── AST/            #  Abstract syntax tree nodes (expressions, statements)
│   │   ├── CodeGen/        #  Code generators (Laravel, React, Postgres, Deployment, Auth)
│   │   ├── Lexer/          #  Tokenizer and token types
│   │   ├── Parser/         #  Recursive descent parser
│   │   ├── Semantic/       #  Scope analysis and symbol resolution
│   │   ├── TypeChecker/    #  Static type validation
│   │   ├── Optimizer/      #  Constant folding and dead code elimination
│   │   ├── PackageManager/ #  Package resolution, lock files, registry client
│   │   └── Registry/       #  Package registry and developer portal
│   └── tests/
│
├── cypher-runtime/         # AI agent runtime
│   └── src/
│       ├── Agent/          #  Agent definitions, lifecycle, task execution
│       ├── DeveloperAgent/ #  Autonomous code generation agent
│       ├── Knowledge/      #  Knowledge engine and retrieval
│       ├── Memory/         #  Short-term, long-term, episodic, semantic, vector memory
│       ├── MultiAgent/     #  Agent teams and multi-agent orchestration
│       ├── Reasoning/      #  Direct, Chain-of-Thought, Tree-of-Thought strategies
│       ├── Tools/          #  Tool registry and execution
│       └── Workflow/       #  Workflow definitions and engine
│
├── cypher-runtime-engine/  # Bytecode VM and execution engine
│   └── src/
│       ├── Bytecode/       #  Bytecode compiler, opcodes, program representation
│       ├── VM/             #  Stack-based virtual machine
│       ├── Memory/         #  Memory manager and garbage collector
│       ├── Concurrency/    #  Coroutine scheduler and worker pool
│       ├── Http/           #  HTTP runtime for web requests
│       ├── AiRuntime/      #  AI model integration runtime
│       ├── Profiler/       #  Benchmarking and profiling
│       └── Sandbox/        #  Security sandbox and package validation
│
├── cypher-cloud/           # Cloud platform
│   └── src/
│       ├── Platform/       #  Core cloud platform services
│       ├── Deployment/     #  Managed deployment orchestration
│       ├── Monitoring/     #  Application and infrastructure monitoring
│       ├── Billing/        #  Usage-based billing
│       ├── Marketplace/    #  Plugin and template marketplace
│       ├── AgentCloud/     #  Managed AI agent hosting
│       ├── ManagedServices/#  Database, cache, queue management
│       └── Security/       #  Cloud security services
│
├── cypher-standard-library/ # Standard library (coming soon)
│   └── ai/                  #  AI/ML utilities
│   └── database/            #  Database helpers
│   └── src/                 #  Core standard library modules
│
├── cypher-ecosystem/       # Community, education, partnerships
├── cypher-foundation/      # Open-source governance and standards
├── cypher-enterprise/      # Enterprise compliance, security, multi-tenancy
├── examples/               # Example .cyp applications
├── tests/                  # End-to-end integration tests
├── cypher.json             # Project configuration
└── composer.json           # PHP package configuration
```

---

## Roadmap

### Phase 1 — Foundation (Current)
- [x] Lexer with full token set
- [x] Recursive descent parser
- [x] Semantic analyzer (scope resolution)
- [x] Type checker
- [x] PHP backend code generator (Laravel 12)
- [x] React frontend code generator (TypeScript + Tailwind)
- [x] PostgreSQL schema generator
- [x] Authentication scaffolding generator
- [x] Deployment generator (Docker + CI/CD)
- [x] Bytecode VM (stack-based)
- [x] Memory manager and garbage collector
- [x] AI agent framework (memory, reasoning, tools, multi-agent)

### Phase 2 — Language Maturity (In Progress)
- [ ] Language Server Protocol (LSP) implementation
- [ ] Syntax highlighting (VS Code, JetBrains, Vim, Sublime)
- [ ] Formatter and linter
- [ ] Debugger integration (Xdebug, Chrome DevTools)
- [ ] Full standard library
- [ ] Package registry launch

### Phase 3 — Developer Experience
- [ ] Interactive REPL
- [ ] Hot module replacement for all tiers
- [ ] Visual project wizard
- [ ] Test framework with auto-generation
- [ ] API documentation generator

### Phase 4 — AI Integration
- [ ] Multi-agent autonomous coding
- [ ] Natural language to CYP translation
- [ ] Self-healing code (agents fix compilation errors)
- [ ] AI-assisted refactoring and optimization

### Phase 5 — Performance
- [ ] JIT compilation
- [ ] Advanced optimizer (SSA form, loop optimizations)
- [ ] WASM compilation target
- [ ] Native binary compilation (via FFI)

### Phase 6 — Cloud Platform
- [ ] Managed hosting (Cypher Cloud)
- [ ] One-command deployment
- [ ] Auto-scaling infrastructure
- [ ] Built-in monitoring and observability

### Phase 7 — Ecosystem Expansion
- [ ] Package registry with 100+ community packages
- [ ] Component marketplace
- [ ] Template library
- [ ] CYP Academy (learning platform)

### Phase 8 — Enterprise
- [ ] Compliance certification suite
- [ ] Enterprise SSO and directory integration
- [ ] Audit logging and governance
- [ ] Multi-tenant enterprise platform

### Phase 9 — Mobile
- [ ] React Native code generation
- [ ] Mobile agent runtime
- [ ] Offline-first architecture

### Phase 10 — Ubiquity
- [ ] IoT and edge device support
- [ ] CYP in the browser (WASM runtime)
- [ ] Decentralized agent networks
- [ ] Cross-language interoperability layer

---

## Contributing

We welcome contributions from the community! CYP is developed under the Cypher Foundation's open-source governance model.

### Getting Started

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Setup

```bash
git clone https://github.com/phynnetmt/cyp.git
cd cyp
composer install
composer test      # Run all PHPUnit tests
php cypher-compiler/bin/cypc build examples/hello.cyp
```

### Coding Standards

- Follow PSR-12 for PHP code
- Run PHPStan before submitting PRs
- All new features must include tests
- Update documentation for API changes
- Use conventional commits (`feat:`, `fix:`, `docs:`, `refactor:`)

### Pull Request Guidelines

- Keep PRs focused on a single concern
- Include before/after examples for visual changes
- Add test coverage for new functionality
- Ensure all existing tests pass
- Reference related issues in the PR description

---

## Security

### Security Policy

CYP takes security seriously. The Cypher Foundation maintains a coordinated vulnerability disclosure program.

### Reporting Vulnerabilities

**Do not report security vulnerabilities in public GitHub issues.**

Please report security issues via email to **security@cyphercode.ai** or through the Cypher Foundation's security program.

You can expect:
- Acknowledgment within 48 hours
- A detailed response within 5 business days
- Regular updates on progress toward resolution
- Credit in security advisories (if desired)

### Security Features

- **Sandboxed Execution:** All user code runs in a sandbox with restricted system access
- **Package Validation:** Registry packages are scanned for malicious code
- **Compliance Frameworks:** SOC 2, ISO 27001, GDPR, HIPAA, PCI-DSS, NIST, FedRAMP
- **Audit Trails:** Every deployment and agent action is logged
- **Dependency Scanning:** Automated vulnerability scanning for generated projects

---

## License

Copyright (c) 2026 Cypher Code AI. All rights reserved.

CYP is released under the **CYP Public License v1.0 (CYP-1.0)** — a permissive open-source license compatible with the MIT license.

Permission is granted to use, copy, modify, merge, publish, and distribute this software for commercial and non-commercial purposes, including in AI systems and automated code generation tools, provided that the copyright notice and license terms are included in all copies or substantial portions of the software.

The software is provided "as is", without warranty of any kind.

---

<p align="center">
  <b>CYP — Cypher Programming Language</b><br>
  <a href="https://github.com/phynnetmt/cyp">GitHub</a> ·
  <a href="https://cyphercode.ai">Website</a> ·
  <a href="https://docs.cyphercode.ai">Documentation</a> ·
  <a href="https://discord.gg/cyphercode">Discord</a> ·
  <a href="https://twitter.com/cyphercode">Twitter</a>
  <br><br>
  <i>Build Anything. Deploy Anywhere. Scale Intelligently.</i>
</p>
