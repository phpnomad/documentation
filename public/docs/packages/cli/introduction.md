---
id: cli-introduction
slug: docs/packages/cli/introduction
title: CLI Package
doc_type: explanation
status: active
language: en
owner: docs-team
last_reviewed: 2026-04-13
summary: The CLI package provides static analysis and code generation tools for PHPNomad projects, producing token-efficient JSONL indexes and scaffolding PHP files from recipe specs.
llm_summary: >
  phpnomad/cli is a developer tooling package with two subsystems. The Indexer parses PHPNomad projects
  via AST analysis (nikic/php-parser) to produce structured JSONL files in a .phpnomad/ directory,
  covering classes, DI bindings, boot sequences, REST controllers, events, tables, facades, and
  dependency graphs. These indexes enable AI agents and developers to query project structure without
  reading source files, with token savings of 13x to 109x on real projects. The Scaffolder generates
  PHP files from JSON recipe specifications and automatically registers new classes in initializers
  via AST mutation. The package includes five commands: index, inspect:di, inspect:routes, context,
  and make.
questions_answered:
  - What is the PHPNomad CLI?
  - What does phpnomad/cli do?
  - How do I install the CLI?
  - What commands does the CLI provide?
  - How does the indexer work?
  - What is the scaffolder?
  - Why use JSONL files instead of reading source code?
  - How does the CLI save tokens for AI agents?
  - What are the requirements for using the CLI?
  - What is the design philosophy behind the CLI?
audience:
  - developers
  - ai-agent-authors
  - devops
tags:
  - cli
  - package-overview
  - static-analysis
  - code-generation
  - tooling
llm_tags:
  - cli-package
  - ast-parsing
  - jsonl-index
  - scaffolding
  - token-efficiency
keywords:
  - phpnomad cli
  - cli package
  - static analysis
  - code scaffolding
  - JSONL index
  - token efficiency
  - AST parsing
related:
  - ../../core-concepts/bootstrapping/introduction
  - ../../core-concepts/bootstrapping/creating-and-managing-initializers
see_also:
  - indexer/introduction
  - scaffolder/introduction
  - commands/introduction
noindex: false
---

# CLI

`phpnomad/cli` gives you two things: **a way to understand your project** and **a way to generate code for it**. It scans PHPNomad projects using AST parsing and produces structured JSONL index files that answer structural questions in a fraction of the tokens it would take to read raw source. It also scaffolds new PHP files from JSON recipe specifications and auto-registers them in your initializers.

The package has two major subsystems:

* **Indexer**: Parses your PHP files via `nikic/php-parser`, walks the `Application -> Bootstrapper -> Initializer` chain, and writes a set of JSONL files to `.phpnomad/`. These files map classes, DI bindings, boot sequences, REST controllers, events, tables, dependency graphs, and more.
* **Scaffolder**: Takes a JSON recipe spec (built-in or custom), resolves variables, generates PHP files with proper namespaces and interface implementations, and mutates your initializer to register the new class automatically.

Five commands expose these subsystems:

| Command | What it does |
|---|---|
| `phpnomad index` | Build the full JSONL index |
| `phpnomad inspect:di` | Display boot sequence and DI bindings |
| `phpnomad inspect:routes` | List REST routes with methods and capabilities |
| `phpnomad context` | Generate a compact project context summary |
| `phpnomad make` | Scaffold code from a recipe spec |

---

## Key ideas at a glance

* **ClassIndex**: Scans all PHP files and builds a registry of classes, interfaces, traits, constructor params, and parent classes.
* **BootSequenceWalker**: Finds Application classes, traces `new Bootstrapper()` calls, and extracts the ordered initializer list with direct container bindings.
* **InitializerAnalyzer**: Parses each initializer to extract contributions from every `Has*` interface (bindings, controllers, listeners, commands, facades, and more).
* **DependencyGraphBuilder**: Builds a unified relationship graph across all edge types and inverts it for reverse lookups. Identifies orphan classes with no relationships.
* **RecipeEngine**: Resolves recipe variables, renders PHP templates, and coordinates initializer mutations for auto-registration.
* **JSONL output**: One JSON object per line, one file per record type. Agents can grep for a specific class without parsing the entire index.

---

## High-level data flow

```
                         phpnomad/cli
                    +---------------------+
                    |                     |
  Project Files     |   AST Parsing       |     .phpnomad/
  (*.php)      ---->|   (nikic/php-parser) |---->  classes.jsonl
                    |         |           |        initializers.jsonl
                    |         v           |        applications.jsonl
                    |   Boot Sequence     |        controllers.jsonl
                    |   Walking           |        dependencies.jsonl
                    |         |           |        dependency-map.jsonl
                    |         v           |        dependents-map.jsonl
                    |   Analyzer          |        tables.jsonl
                    |   Pipeline          |        events.jsonl
                    |         |           |        commands.jsonl
                    |         v           |        facades.jsonl
                    |   Dependency        |        orphans.jsonl
                    |   Graph Builder     |        meta.json
                    +---------------------+

  Recipe Spec       +---------------------+
  (JSON)       ---->|   Scaffolder        |---->  Generated PHP files
                    |     |               |       + initializer mutations
                    |     v               |
                    |   Template Renderer |
                    |     |               |
                    |     v               |
                    |   Initializer       |
                    |   Mutator (AST)     |
                    +---------------------+
```

The indexer pipeline reads your project files, parses them into ASTs, walks the boot sequence, runs a series of analyzers, and writes everything to JSONL. The scaffolder reads a recipe spec, renders PHP templates, writes the output files, and patches your initializer to register the new class.

---

## Installation

```bash
composer require phpnomad/cli --dev
```

This installs the `phpnomad` binary into `vendor/bin/`. You can run it directly:

```bash
vendor/bin/phpnomad index --path=.
```

For system-wide access, symlink the binary:

```bash
ln -s /path/to/phpnomad/cli/bin/phpnomad ~/.local/bin/phpnomad
```

Then from any project directory:

```bash
phpnomad index --path=.
```

---

## Requirements

- **PHP 8.2+**
- **nikic/php-parser ^5.0**: AST parsing engine
- **Target project must use the PHPNomad Bootstrapper pattern**: The indexer traces the `Application -> Bootstrapper -> Initializer` chain to discover bindings, controllers, and other contributions. Projects that don't follow this pattern won't produce meaningful index results.

---

## Why use this package

### Token efficiency for AI agents

The primary motivation behind the CLI is token savings. Instead of reading PHP source files and grepping across a codebase, an AI agent can query a single JSONL file and get a precise, structured answer.

Here are benchmarks from a real project (Siren, 1,019 classes, 69 events, 27 tables):

| Query | Index size | Raw source | Savings |
|---|---|---|---|
| What depends on `AllocateDistribution`? | 1.1 KB | 75 KB | **67x** |
| What injects `EventStrategy`? | 7 KB | 394 KB | **54x** |
| All task handlers with task mappings | 0.3 KB | 37 KB | **109x** |
| Boot sequence + initializer contributions | 10 KB | 315 KB | **32x** |
| All DI bindings with resolution chains | 7 KB | 93 KB | **13x** |
| What implements `DataModel`? (36 classes) | 3 KB | 47 KB | **14x** |
| All events with IDs (69 events) | 23 KB | 73 KB | **3x** |
| All table schemas (27 tables) | 19 KB | 42 KB | **2x** |
| Unreferenced classes (50 orphans) | 6 KB | N/A | impossible without index |

At roughly 4 bytes per token, a reverse lookup on `EventStrategy` drops from ~98,000 tokens to ~1,750 tokens. The savings are largest for reverse lookups (what depends on X?) because without the index, the agent must grep and read every file in the project.

### Structural understanding without reading source

The JSONL index gives you a complete structural map of your application. You can answer questions like "what initializers register controllers?" or "what classes have no relationships?" without opening a single PHP file.

### Automated scaffolding with auto-registration

The `make` command generates new PHP files and automatically registers them in the correct initializer. If the initializer doesn't have the required method yet (for example, `getListeners()` for a new event listener), the scaffolder creates the method and adds the corresponding `Has*` interface. This saves you from the boilerplate of manually wiring new classes into the boot sequence.

---

## When to use this package

Use `phpnomad/cli` when:

- **You're building AI-assisted workflows** around a PHPNomad project and need token-efficient project introspection.
- **You want to scaffold new code** (listeners, controllers, events, commands) without writing boilerplate by hand.
- **You need to audit your project structure**, find orphan classes, or understand dependency chains.
- **You're onboarding** to an unfamiliar PHPNomad codebase and want to see the full boot sequence, DI bindings, and REST routes at a glance.

You don't need this package if your project doesn't use the PHPNomad Bootstrapper pattern. The indexer relies on tracing the `Application -> Bootstrapper -> Initializer` chain, so non-PHPNomad projects won't benefit from the index pipeline.

---

## Design philosophy

**Read-side (introspection) is prioritized over write-side (scaffolding).** Introspection commands save tokens on every AI session by providing structured data about the project. Scaffolding saves tokens only when creating new code. Both matter, but introspection is the higher-leverage starting point.

**JSONL over nested JSON.** Each record type gets its own file with one JSON object per line. This lets agents grep for specific classes without parsing the entire index, and keeps token costs proportional to what's being queried. A single `grep "PayoutDatastore" .phpnomad/dependents-map.jsonl` answers "what depends on this interface?" without touching any other data.

**Static analysis, not runtime reflection.** The CLI parses PHP files via AST without executing them. No database connection, no bootstrap, no platform dependencies. It can index a WordPress plugin, a standalone app, or a test harness identically.

---

## Quick examples

### Build an index

```bash
phpnomad index --path=/path/to/project
```

This creates a `.phpnomad/` directory with JSONL files covering all indexed data.

### Inspect the boot sequence

```bash
phpnomad inspect:di --path=.
```

```
Application: Siren\SaaS\Application (saas/Application.php)

Boot sequence (74 initializers):
  #1   Core\Bootstrap\CoreInitializer (vendor)    1 binding
  #2   SaaS\SaaSInitializer                       10 bindings
  ...

Summary
  4 application(s)
  74 initializers
  244 bindings
  110 controllers
```

### Scaffold a new event listener

```bash
phpnomad make --from=listener '{"name":"SendWelcomeEmail","event":"App\\Events\\UserCreated","initializer":"App\\AppInit"}'
```

This generates the listener PHP file, adds the listener registration to `AppInit`, and (if needed) adds the `HasListeners` interface and `getListeners()` method to the initializer class.

---

## The index output

When you run `phpnomad index`, the CLI writes these files to `.phpnomad/`:

```
.phpnomad/
  meta.json              # Summary counts
  classes.jsonl          # One class per line (FQCN, interfaces, traits, constructor params)
  initializers.jsonl     # One initializer per line (bindings, controllers, listeners, commands)
  applications.jsonl     # One application per line (boot sequence, pre/post bindings)
  controllers.jsonl      # One controller per line (endpoint, method, capabilities)
  commands.jsonl         # One command per line (signature, description)
  dependencies.jsonl     # One dependency tree per line (recursive resolution chain)
  tables.jsonl           # One table per line (name, columns, types, foreign keys)
  events.jsonl           # One event per line (event ID, payload properties)
  graphql-types.jsonl    # One GraphQL type per line (SDL, resolvers)
  facades.jsonl          # One facade per line (proxied interface)
  task-handlers.jsonl    # One handler per line (task class, task ID)
  mutations.jsonl        # One mutation handler per line (actions, adapter info)
  dependency-map.jsonl   # What each class depends on (all relationship types)
  dependents-map.jsonl   # What depends on each class (reverse lookup)
  orphans.jsonl          # Classes with no relationships in either direction
```

Each line is a self-contained JSON object. The dependency-map and dependents-map cover all edge types: constructor injection, interface implementation, inheritance, trait usage, event listeners, task handlers, facade proxies, DI bindings, and mutation adapters.

See [Indexer Output Format](indexer/output-format) for the full field reference.

---

## Package components

### Indexer

The indexer pipeline is the core of the CLI. It walks your project's boot sequence, runs a chain of analyzers (ClassIndex, BootSequenceWalker, InitializerAnalyzer, ControllerAnalyzer, DependencyResolver, DependencyGraphBuilder, and others), and writes structured JSONL output.

See [Indexer](indexer/introduction) for the full pipeline documentation.

### Scaffolder

The scaffolder takes JSON recipe specs, resolves variables against your project, renders PHP templates, and mutates initializers to register new classes. It supports built-in recipes for common patterns (listener, event, command, controller) and custom recipe files for more complex scaffolding.

See [Scaffolder](scaffolder/introduction) for recipe-driven code generation.

### Commands

Five commands expose the indexer and scaffolder: `index`, `inspect:di`, `inspect:routes`, `context`, and `make`. Each accepts a `--path` flag pointing to the target project.

See [Commands](commands/introduction) for the full command reference.

---

## Relationship to other packages

- **[phpnomad/console](../console/introduction)**: Provides the `Command` and `Input` interfaces that the CLI's commands implement.
- **[phpnomad/symfony-console-integration](../symfony-console-integration/introduction)**: Bridges PHPNomad's console interfaces to Symfony Console for actual command execution.
- **[phpnomad/di-container](../di-container/introduction)**: The DI container used internally by the CLI application.
- **[phpnomad/utils](../utils/introduction)**: Utility functions used throughout the CLI codebase.
- **[Bootstrapper pattern](../../core-concepts/bootstrapping/introduction)**: The boot sequence pattern the indexer traces. Understanding this pattern is essential for making sense of the index output.

---

## Next steps

- **Want to understand the index pipeline?** Start with [Indexer](indexer/introduction).
- **Need the JSONL field reference?** See [Indexer Output Format](indexer/output-format).
- **Ready to scaffold code?** Read [Scaffolder](scaffolder/introduction).
- **Looking for a specific command?** Check [Commands](commands/introduction).
- **New to PHPNomad?** The [Bootstrapping](../../core-concepts/bootstrapping/introduction) docs explain the `Application -> Bootstrapper -> Initializer` pattern the CLI builds on.
