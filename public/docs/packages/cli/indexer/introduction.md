---
id: cli-indexer-introduction
slug: docs/packages/cli/indexer/introduction
title: Indexer
doc_type: explanation
status: active
language: en
owner: docs-team
last_reviewed: 2026-04-13
summary: The indexer reconstructs the full PHPNomad boot sequence through AST parsing, producing a structured project index without executing any PHP code.
llm_summary: >
  The PHPNomad CLI indexer is a 13-step static analysis pipeline that parses PHP source files using
  nikic/php-parser to reconstruct the entire boot sequence, DI bindings, REST controllers, CLI commands,
  database tables, events, GraphQL types, facades, task handlers, and mutations. It produces JSONL output
  files in a .phpnomad directory. The pipeline starts with ClassIndex scanning all PHP files, then
  BootSequenceWalker finding Application classes, then InitializerAnalyzer extracting Has* interface
  contributions, followed by specialized analyzers for each subsystem. DependencyResolver builds recursive
  dependency trees, and DependencyGraphBuilder produces a unified relationship graph with 9 edge types
  and their inverses. Vendor packages are resolved via Composer autoload_classmap and PSR-4 mappings.
  No PHP code is executed, no database is required, and no platform dependencies are needed.
questions_answered:
  - What does the PHPNomad indexer do?
  - How does the indexer analyze a project without running it?
  - What are the 13 steps of the indexing pipeline?
  - What does each analyzer extract?
  - How does the indexer handle vendor packages?
  - What is the difference between static analysis and runtime reflection?
  - What are the 9 edge types in the dependency graph?
  - How does the dependency graph builder identify orphans?
  - What output does the indexer produce?
audience:
  - developers
  - backend engineers
  - framework users
tags:
  - cli
  - indexer
  - static-analysis
  - package-overview
llm_tags:
  - phpnomad-cli-indexer
  - ast-parsing
  - boot-sequence
  - dependency-graph
keywords:
  - phpnomad indexer
  - static analysis
  - AST parsing
  - boot sequence
  - dependency graph
  - project index
related:
  - ../introduction
  - ../commands/introduction
  - ../scaffolder/introduction
see_also:
  - output-format
  - ../commands/introduction
noindex: false
---

# Indexer

The indexer is the analytical core of the PHPNomad CLI. It reconstructs your entire project's boot sequence, DI bindings, REST routes, CLI commands, database schemas, events, and more through **static analysis**. It reads PHP files, parses them into abstract syntax trees, and extracts structured data. No PHP code is executed. No database connection is needed. No platform runtime is required.

The result is a complete, queryable index of your project written to a `.phpnomad` directory as a set of JSONL files.

---

## Why static analysis

PHPNomad applications are assembled at runtime through a boot sequence: the `Application` creates a `Bootstrapper`, passes it a list of `Initializer` classes, and each initializer declares what it contributes to the container. To understand the full picture at runtime, you would need to bootstrap the application, which means having a database, a web server, platform integrations, and every dependency satisfied.

The indexer sidesteps all of that. It uses [nikic/php-parser](https://github.com/nikic/PHP-Parser) to parse your PHP files into AST nodes, then walks those trees to extract the same information that the runtime would produce. The output is deterministic and reproducible. You can run it in CI, on a fresh clone, or on a machine that has never connected to your production database.

This matters for three reasons:

1. **Tooling without setup.** The [scaffolder](../scaffolder/introduction) uses the index for pre-flight validation before generating code. The `context` command uses it to produce AI-readable project summaries.
2. **Portability.** The index files are plain JSON, greppable and diffable. They travel with the project in version control if you want them to.
3. **Speed.** Parsing is fast. A typical project indexes in under a second.

---

## The 13-step pipeline

The indexer runs as a sequential pipeline. Each step builds on the output of previous steps, narrowing from broad file scanning to specialized subsystem analysis, and finishing with a unified dependency graph.

```
PHP Source Files
      |
      v
+------------------+
|  1. ClassIndex    |   Scan all .php files, build class registry
+------------------+
      |
      v
+------------------------+
|  2. BootSequenceWalker |   Find Application classes, extract boot sequences
+------------------------+
      |
      v
+-------------------------+
|  3. InitializerAnalyzer |   Parse each Initializer for Has* contributions
+-------------------------+
      |
      +----+----+----+----+----+----+----+----+
      |    |    |    |    |    |    |    |    |
      v    v    v    v    v    v    v    v    v
   +----+----+----+----+----+----+----+----+----+
   | 4  | 5  | 6  | 7  | 8  | 9  | 10 | 11 | 12 |
   +----+----+----+----+----+----+----+----+----+
   Ctrl  Cmd  Dep  Tbl  Evt  GQL  Fcd  Task  Mut
      |    |    |    |    |    |    |    |    |
      +----+----+----+----+----+----+----+----+
      |
      v
+---------------------------+
|  13. DependencyGraphBuilder |   Build unified graph, invert edges, find orphans
+---------------------------+
      |
      v
   JSONL Output (.phpnomad/)
```

Each step is described below.

---

### Step 1: ClassIndex

`ClassIndex` scans every `.php` file in your project (excluding `vendor/`, `tests/`, and `node_modules/`) and builds a registry keyed by fully qualified class name.

For each class it records:

- **FQCN** (fully qualified class name)
- **File path** (relative to project root)
- **Implements** (list of interface FQCNs)
- **Traits** (list of trait FQCNs)
- **Constructor parameters** (name, type, whether the type is a builtin like `string` or `int`)
- **Parent class** (FQCN of the extended class, if any)
- **Abstract flag** (whether the class is abstract)
- **Description** (first non-annotation line from the class docblock)

This registry is the foundation for every subsequent step. Other analyzers look up classes here by FQCN to find their interfaces, traits, and constructor signatures.

---

### Step 2: BootSequenceWalker

`BootSequenceWalker` identifies Application classes by scanning for `new Bootstrapper()` calls. A class is considered an Application if it instantiates `PHPNomad\Loader\Bootstrapper` anywhere in its methods.

For each Application it extracts:

- **Pre-bootstrap container bindings** (`$this->container->bind(...)` calls before the Bootstrapper instantiation)
- **Bootstrapper calls** (the ordered list of `Initializer` class references passed to the constructor)
- **Post-bootstrap container bindings** (bind calls after the Bootstrapper)

The walker follows method calls and variable assignments to resolve the initializer list. If your Application has a method like `getCoreInitializers()` that returns an array of `new SomeInitializer()` expressions, the walker traces into that method and extracts the references in order. It also handles `array_merge()` patterns and spread operators (`...$this->getBaseInitializers()`).

When a reference cannot be statically resolved (for example, an initializer passed as a parameter), it is marked as **dynamic**.

---

### Step 3: InitializerAnalyzer

`InitializerAnalyzer` parses each Initializer class found in Step 2. It checks which `Has*` interfaces the initializer implements and extracts the return values of the corresponding methods.

The 10 recognized interfaces and their methods are:

| Interface | Method | What it contributes |
|-----------|--------|---------------------|
| `HasClassDefinitions` | `getClassDefinitions()` | DI bindings (concrete to abstract mappings) |
| `HasControllers` | `getControllers()` | REST controller class references |
| `HasListeners` | `getListeners()` | Event-to-listener mappings |
| `HasEventBindings` | `getEventBindings()` | Event binding mappings |
| `HasCommands` | `getCommands()` | CLI command class references |
| `HasMutations` | `getMutations()` | Mutation handler mappings |
| `HasTaskHandlers` | `getTaskHandlers()` | Task-to-handler mappings |
| `HasTypeDefinitions` | `getTypeDefinitions()` | GraphQL type definition references |
| `HasUpdates` | `getRoutines()` | Update routine references |
| `HasFacades` | `getFacades()` | Facade class references |

For `HasClassDefinitions`, the analyzer extracts the full binding map: which concrete class resolves which abstract interface(s). Each binding records its source (the initializer FQCN), the file it came from, and whether it is a `declarative` binding (from an initializer's `getClassDefinitions()`) or an `imperative` binding (from a direct `$this->container->bind()` call in the Application).

---

### Step 4: ControllerAnalyzer

`ControllerAnalyzer` parses each controller class referenced in Step 3. It extracts:

- **Endpoint path** (from `getEndpoint()` or `getEndpointTail()` if the controller uses `WithEndpointBase`)
- **HTTP method** (from `getMethod()`, resolving both string returns like `'GET'` and enum references like `Method::Get`)
- **Capability flags**: whether the controller implements `HasMiddleware`, `HasValidations`, or `HasInterceptors`

---

### Step 5: CommandAnalyzer

`CommandAnalyzer` parses each CLI command class. It extracts:

- **Signature** (from `getSignature()`, the command name and argument pattern)
- **Description** (from `getDescription()`, the human-readable help text)

---

### Step 6: DependencyResolver

`DependencyResolver` builds recursive dependency trees for every binding in the project. It works like this:

1. Merge all bindings from all Applications into a single binding map, respecting boot order (pre-bootstrap bindings first, then initializer bindings in sequence, then post-bootstrap bindings).
2. For each abstract in the map, resolve its concrete class.
3. Look up the concrete class's constructor parameters.
4. For each non-builtin parameter type, recursively resolve it through the binding map.
5. Continue until all leaves are resolved, a circular dependency is detected, or the depth cap (10 levels) is reached.

Each node in the tree records the abstract FQCN, the concrete FQCN, the source (which initializer or Application provided the binding), and the resolution type (`declarative`, `imperative`, `auto-wired`, `circular`, or `unresolved`).

---

### Step 7: TableAnalyzer

`TableAnalyzer` finds all classes that extend `PHPNomad\Database\Abstracts\Table` and extracts their schemas:

- **Table name** (from `getUnprefixedName()` or `getName()`)
- **Columns** (from `getColumns()`), including:
  - Column name, SQL type, type arguments (like `VARCHAR(255)`)
  - Column attributes (`NOT NULL`, `DEFAULT`, etc.)
  - Factory columns (`PrimaryKeyFactory`, `DateCreatedFactory`, `DateModifiedFactory`, `ForeignKeyFactory`) are resolved to their actual column definitions
  - Foreign key references (table and column)

---

### Step 8: EventAnalyzer

`EventAnalyzer` finds all classes implementing `PHPNomad\Events\Interfaces\Event` and extracts:

- **Event string ID** (from the static `getId()` method return value)
- **Payload properties** (derived from the constructor parameter names and types)

---

### Step 9: GraphQLTypeAnalyzer

`GraphQLTypeAnalyzer` parses type definition classes referenced by `HasTypeDefinitions`. It extracts:

- **SDL string** (the raw GraphQL schema definition from `getSdl()`)
- **Resolver mappings** (from `getResolvers()`, a nested map of `TypeName => fieldName => ResolverClass`)

---

### Step 10: FacadeAnalyzer

`FacadeAnalyzer` parses each facade class referenced by `HasFacades`. It extracts:

- **Proxied interface** (the FQCN returned by the `abstractInstance()` method, which tells the container what interface the facade proxies)

---

### Step 11: TaskHandlerAnalyzer

`TaskHandlerAnalyzer` resolves the mappings from `HasTaskHandlers`. For each handler it records:

- **Handler FQCN** and file
- **Task class FQCN** (the task type this handler processes)
- **Task runtime ID** (from the task class's static `getId()` method)

---

### Step 12: MutationAnalyzer

`MutationAnalyzer` examines mutation handler classes from `HasMutations`. It detects:

- **Actions** (the list of action strings this mutator handles)
- **Adapter trait usage** (whether the class uses `CanMutateFromAdapter`)
- **Adapter class** (the FQCN of the `$mutationAdapter` constructor parameter or property, if the adapter trait is present)

---

### Step 13: DependencyGraphBuilder

`DependencyGraphBuilder` is the final step. It takes everything collected by the previous 12 steps and builds a unified relationship graph.

It collects edges from 9 distinct relationship types:

| Edge type | Source | Target | Derived from |
|-----------|--------|--------|-------------|
| `injects` | Class | Constructor param type | Constructor parameters |
| `implements` | Class | Interface | `implements` keyword |
| `extends` | Class | Parent class | `extends` keyword |
| `uses-trait` | Class | Trait | `use` statements |
| `listens-to` | Listener class | Event class | `HasListeners` mappings |
| `handles-task` | Handler class | Task class | `HasTaskHandlers` mappings |
| `proxies` | Facade class | Interface | `abstractInstance()` return |
| `resolves-to` | Abstract FQCN | Concrete FQCN | DI binding map |
| `mutates-via` | Mutation class | Adapter class | `CanMutateFromAdapter` trait |

The builder produces three outputs:

1. **Dependency map** (top-down). For each class, what does it depend on? Keyed by source FQCN, each entry lists edges pointing to targets.
2. **Dependents map** (bottom-up). For each class, what depends on it? This is the inverted graph. Edge types are inverted too: `injects` becomes `injected-by`, `implements` becomes `implemented-by`, `extends` becomes `extended-by`, and so on.
3. **Orphans**. Classes that appear in neither the dependency map nor the dependents map. These have no relationships with any other class in the project. They are candidates for dead code removal.

The full inversion map:

| Forward edge | Inverted edge |
|-------------|---------------|
| `injects` | `injected-by` |
| `implements` | `implemented-by` |
| `extends` | `extended-by` |
| `uses-trait` | `trait-used-by` |
| `listens-to` | `listened-by` |
| `handles-task` | `task-handled-by` |
| `proxies` | `proxied-by` |
| `resolves-to` | `resolved-from` |
| `mutates-via` | `adapter-for` |

---

## How vendor packages are resolved

The ClassIndex only scans your project's own source files by default. It excludes `vendor/`, `tests/`, and `node_modules/`. But initializers, controllers, and other classes often reference types that live in vendor packages.

When an analyzer needs a class that is not in the project index, it falls back to `ClassIndex::resolveFromVendor()`. This method resolves the file path in two ways:

1. **Composer's autoload classmap.** It reads `vendor/composer/autoload_classmap.php`, which is a flat array mapping every FQCN to its absolute file path. This is the fastest lookup and covers any class that Composer has mapped.
2. **PSR-4 namespace mappings.** If the classmap does not contain the FQCN, it reads `vendor/composer/autoload_psr4.php` and converts the namespace prefix to a directory path. It then checks whether the corresponding file exists.

Once the file is found, it is parsed with the same AST pipeline as project files. Resolved vendor classes are cached in memory so they are only parsed once per indexing run.

This means the indexer can trace dependency trees through framework packages, third-party libraries, and any other Composer dependency without requiring those packages to be indexed upfront.

---

## Output

The indexer writes its output to a `.phpnomad` directory at the project root. Each data type gets its own JSONL file (one JSON object per line), which makes them efficient to grep and stream.

See [Output Format](output-format) for a complete reference of every file, its fields, and example queries.

---

## Running the indexer

The indexer is invoked through the `phpnomad index` command. See the [command reference](../commands/introduction) for usage details, flags, and examples.

---

## How the scaffolder uses the index

The [scaffolder](../scaffolder/introduction) loads the index before generating code. Its `PreflightValidator` reads the class registry and binding map to verify that referenced interfaces exist, that bindings do not conflict, and that the namespace you are scaffolding into follows the project's conventions. This means the scaffolder can catch problems before writing any files.
