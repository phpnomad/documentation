---
id: cli-indexer-output-format
slug: docs/packages/cli/indexer/output-format
title: JSONL Output Format
doc_type: reference
status: active
language: en
owner: docs-team
last_reviewed: 2026-04-13
summary: Reference for the .phpnomad/ directory structure, all 16 index files, their JSON schemas, and practical querying patterns.
llm_summary: >
  Complete reference for the JSONL index output produced by `phpnomad index`. Covers the .phpnomad/ directory structure
  with all 16 files (meta.json, classes.jsonl, initializers.jsonl, applications.jsonl, controllers.jsonl, commands.jsonl,
  dependencies.jsonl, tables.jsonl, events.jsonl, graphql-types.jsonl, facades.jsonl, task-handlers.jsonl, mutations.jsonl,
  dependency-map.jsonl, dependents-map.jsonl, orphans.jsonl). Includes JSON examples for each file, the 9 edge types used
  in the dependency graph, inverted edge types for the dependents map, grep-based querying patterns, and token efficiency
  benchmarks showing 32x to 109x savings over raw source.
questions_answered:
  - What files does the indexer produce?
  - What is the JSON schema for each index file?
  - How do I query the index with grep?
  - What are the dependency graph edge types?
  - What is the difference between dependency-map and dependents-map?
  - How do I find orphaned classes?
  - How much smaller is the index than raw source?
  - Why does the indexer use JSONL instead of a single JSON file?
  - What fields does classes.jsonl contain?
  - How do I find reverse dependencies for an interface?
audience:
  - developers
  - AI agents
  - devops engineers
tags:
  - cli
  - indexer
  - output-format
  - jsonl
  - dependency-graph
llm_tags:
  - cli-indexer
  - jsonl-format
  - dependency-map
  - dependents-map
  - orphan-detection
  - token-efficiency
keywords:
  - phpnomad index output
  - jsonl format
  - .phpnomad directory
  - dependency graph
  - index files
  - grep queries
  - token efficiency
related:
  - introduction
  - ../introduction
  - ../commands/introduction
see_also:
  - introduction
noindex: false
---

# JSONL Output Format

When you run `phpnomad index`, the indexer writes a `.phpnomad/` directory at the project root containing 16 files. One file is plain JSON. The other 15 are JSONL, meaning one JSON object per line.

This page is the reference for every file in that directory, including exact JSON shapes, the dependency graph edge types, and practical querying patterns.

---

## Why JSONL

The index is designed to be consumed by AI agents and developer tools, not by humans staring at a web UI. JSONL was chosen over a single large JSON file for three reasons.

**Grep-friendly.** Each line is a self-contained JSON object. You can `grep` for a class name and get back just the lines that mention it, without parsing the entire file. This matters when the index contains thousands of classes.

**Token-efficient.** An AI agent that needs to understand one controller does not need to read the entire index. It greps for that controller's FQCN and gets back one line. The cost in tokens is proportional to what is actually queried, not what exists in the project.

**Proportional cost.** A project with 50 classes produces a small index. A project with 2,000 classes produces a larger one. But a single query against either project returns roughly the same amount of data.

---

## Directory structure

After indexing, your project looks like this:

```
your-project/
  .phpnomad/
    meta.json
    classes.jsonl
    initializers.jsonl
    applications.jsonl
    controllers.jsonl
    commands.jsonl
    dependencies.jsonl
    tables.jsonl
    events.jsonl
    graphql-types.jsonl
    facades.jsonl
    task-handlers.jsonl
    mutations.jsonl
    dependency-map.jsonl
    dependents-map.jsonl
    orphans.jsonl
    phpnomad-cli.md
```

The `phpnomad-cli.md` file is an auto-generated cheat sheet that summarizes the index and common queries. The 16 data files are described below.

---

## File reference

### meta.json

The only non-JSONL file. A single JSON object with summary counts for the entire index.

```json
{
  "projectPath": "/home/user/projects/my-app",
  "indexedAt": "2026-04-13T14:22:07-05:00",
  "counts": {
    "classes": 1019,
    "applications": 1,
    "initializers": 24,
    "bindings": 187,
    "controllers": 42,
    "listeners": 18,
    "resolvedControllers": 42,
    "resolvedCommands": 7,
    "dependencyTrees": 187,
    "resolvedTables": 15,
    "resolvedEvents": 12,
    "resolvedGraphQLTypes": 0,
    "resolvedFacades": 3,
    "resolvedTaskHandlers": 5,
    "resolvedMutations": 4,
    "dependencyMapNodes": 843,
    "dependentsMapNodes": 671,
    "orphans": 23
  }
}
```

Use this file to get a quick overview of the project's size and composition without reading any JSONL files.

---

### classes.jsonl

Every PHP class discovered in the project. One line per class.

**Key fields:** `fqcn`, `file`, `line`, `implements`, `traits`, `constructorParams`, `isAbstract`, `parentClass`, `description`

```json
{"fqcn":"App\\Services\\PayoutService","file":"lib/Services/PayoutService.php","line":12,"implements":["App\\Contracts\\PayoutServiceInterface"],"traits":[],"constructorParams":[{"name":"datastore","type":"App\\Datastores\\Interfaces\\PayoutDatastoreInterface","isBuiltin":false}],"isAbstract":false,"parentClass":null,"description":"Handles payout calculations and distribution."}
```

```json
{"fqcn":"App\\Models\\Payout","file":"lib/Models/Payout.php","line":8,"implements":["PHPNomad\\Datamodel\\Interfaces\\DataModel"],"traits":["PHPNomad\\Datamodel\\Traits\\WithDataModel"],"constructorParams":[],"isAbstract":false,"parentClass":null,"description":null}
```

Each `constructorParams` entry contains `name`, `type` (the FQCN of the type hint), and `isBuiltin` (true for scalar types like `string`, `int`, `bool`).

---

### initializers.jsonl

Each initializer in the project, with its bindings, controllers, listeners, commands, and other contributions.

**Key fields:** `fqcn`, `file`, `isVendor`, `implementedInterfaces`, `classDefinitions`, `controllers`, `listeners`, `eventBindings`, `commands`, `mutations`, `taskHandlers`, `typeDefinitions`, `updates`, `facades`, `hasLoadCondition`, `isLoadable`

```json
{"fqcn":"App\\Initializers\\PayoutInitializer","file":"lib/Initializers/PayoutInitializer.php","isVendor":false,"implementedInterfaces":["PHPNomad\\Di\\Interfaces\\HasClassDefinitions","PHPNomad\\Rest\\Interfaces\\HasControllers","PHPNomad\\Event\\Interfaces\\HasListeners"],"classDefinitions":[{"concrete":"App\\Services\\PayoutService","abstracts":["App\\Contracts\\PayoutServiceInterface"],"source":"App\\Initializers\\PayoutInitializer","sourceFile":"lib/Initializers/PayoutInitializer.php","bindingType":"declarative"}],"controllers":["App\\Controllers\\GetPayoutsController","App\\Controllers\\CreatePayoutController"],"listeners":{"App\\Events\\SaleTriggered":["App\\Listeners\\CalculatePayoutListener"]},"eventBindings":[],"commands":[],"mutations":{},"taskHandlers":{},"typeDefinitions":[],"updates":[],"facades":[],"hasLoadCondition":false,"isLoadable":true}
```

The `listeners` field is a map from event FQCN to an array of handler FQCNs. The `classDefinitions` field contains the DI bindings this initializer registers.

---

### applications.jsonl

Application classes that define the boot sequence. Most projects have exactly one.

**Key fields:** `fqcn`, `file`, `preBootstrapBindings`, `bootstrapperCalls`, `postBootstrapBindings`

```json
{"fqcn":"App\\Application","file":"lib/Application.php","preBootstrapBindings":[{"concrete":"App\\Strategies\\MySqlQueryStrategy","abstracts":["PHPNomad\\Database\\Interfaces\\QueryStrategy"],"source":"App\\Application","sourceFile":"lib/Application.php","bindingType":"declarative"}],"bootstrapperCalls":[{"method":"use","line":34,"initializers":[{"fqcn":"App\\Initializers\\PayoutInitializer","isDynamic":false}]},{"method":"use","line":35,"initializers":[{"fqcn":"App\\Initializers\\ReportInitializer","isDynamic":false}]}],"postBootstrapBindings":[]}
```

The `bootstrapperCalls` array preserves the order that initializers are loaded. Each call records the method name, line number, and the initializer references passed to it.

---

### controllers.jsonl

Resolved REST controllers with their endpoints, HTTP methods, and capabilities.

**Key fields:** `fqcn`, `file`, `endpoint`, `endpointTail`, `method`, `usesEndpointBase`, `hasMiddleware`, `hasValidations`, `hasInterceptors`

```json
{"fqcn":"App\\Controllers\\GetPayoutsController","file":"lib/Controllers/GetPayoutsController.php","endpoint":"/api/v1/payouts","endpointTail":null,"method":"GET","usesEndpointBase":false,"hasMiddleware":true,"hasValidations":true,"hasInterceptors":false}
```

```json
{"fqcn":"App\\Controllers\\CreatePayoutController","file":"lib/Controllers/CreatePayoutController.php","endpoint":null,"endpointTail":"/create","method":"POST","usesEndpointBase":true,"hasMiddleware":true,"hasValidations":true,"hasInterceptors":true}
```

When `usesEndpointBase` is true and `endpoint` is null, the controller derives its path from a shared base. The `endpointTail` is appended to that base.

---

### commands.jsonl

CLI commands registered through initializers.

**Key fields:** `fqcn`, `file`, `signature`, `description`

```json
{"fqcn":"App\\Commands\\ProcessPayoutsCommand","file":"lib/Commands/ProcessPayoutsCommand.php","signature":"payouts:process {--dry-run}","description":"Process all pending payouts"}
```

---

### dependencies.jsonl

Recursive dependency trees for each DI binding. Shows how interfaces resolve to concrete classes and what those concrete classes depend on in turn.

**Key fields:** `abstract`, `concrete`, `source`, `resolutionType`, `dependencies`

```json
{"abstract":"App\\Contracts\\PayoutServiceInterface","concrete":"App\\Services\\PayoutService","source":"App\\Initializers\\PayoutInitializer","resolutionType":"bound","dependencies":[{"abstract":"App\\Datastores\\Interfaces\\PayoutDatastoreInterface","concrete":"App\\Datastores\\PayoutDatastore","source":"App\\Initializers\\PayoutInitializer","resolutionType":"bound","dependencies":[]}]}
```

The `resolutionType` field is `bound` when the interface has an explicit DI binding, or `unresolved` when no binding was found.

---

### tables.jsonl

Database table definitions with their column schemas.

**Key fields:** `fqcn`, `file`, `tableName`, `columns`

```json
{"fqcn":"App\\Tables\\PayoutsTable","file":"lib/Tables/PayoutsTable.php","tableName":"payouts","columns":[{"name":"id","type":"BIGINT","typeArgs":null,"factory":"PrimaryKeyFactory","attributes":["UNSIGNED","NOT NULL","AUTO_INCREMENT"]},{"name":"userId","type":"BIGINT","typeArgs":null,"factory":null,"attributes":["UNSIGNED","NOT NULL"]},{"name":"amount","type":"DECIMAL","typeArgs":[10,2],"factory":null,"attributes":["NOT NULL"]},{"name":"status","type":"VARCHAR","typeArgs":[50],"factory":null,"attributes":["NOT NULL","DEFAULT 'pending'"]},{"name":"createdAt","type":"TIMESTAMP","typeArgs":null,"factory":"DateCreatedFactory","attributes":["NOT NULL","DEFAULT CURRENT_TIMESTAMP"]}]}
```

Each column object includes `name`, `type`, optional `typeArgs` (for types like `VARCHAR(255)` or `DECIMAL(10,2)`), an optional `factory` name, and an `attributes` array with constraints.

---

### events.jsonl

Event classes with their event IDs and payload properties.

**Key fields:** `fqcn`, `file`, `eventId`, `properties`

```json
{"fqcn":"App\\Events\\SaleTriggered","file":"lib/Events/SaleTriggered.php","eventId":"sale_triggered","properties":[{"name":"saleId","type":"int"},{"name":"amount","type":"float"},{"name":"collaboratorId","type":"int"}]}
```

The `eventId` is the string identifier used in event dispatching. The `properties` array lists the payload fields with their types.

---

### graphql-types.jsonl

GraphQL type definitions with their SDL and resolver mappings.

**Key fields:** `fqcn`, `file`, `sdl`, `resolvers`

```json
{"fqcn":"App\\GraphQL\\Types\\PayoutType","file":"lib/GraphQL/Types/PayoutType.php","sdl":"type Payout {\n  id: ID!\n  amount: Float!\n  status: String!\n  createdAt: String!\n}","resolvers":{"amount":{"App\\GraphQL\\Resolvers\\PayoutAmountResolver":true}}}
```

The `sdl` field contains the raw GraphQL schema definition. The `resolvers` field maps field names to their resolver FQCNs.

---

### facades.jsonl

Facade classes and the interfaces they proxy.

**Key fields:** `fqcn`, `file`, `proxiedInterface`

```json
{"fqcn":"App\\Facades\\Transactions","file":"lib/Facades/Transactions.php","proxiedInterface":"App\\Contracts\\TransactionServiceInterface"}
```

---

### task-handlers.jsonl

Task handler mappings that connect task classes to their handler implementations.

**Key fields:** `handlerFqcn`, `handlerFile`, `taskClass`, `taskId`, `taskFile`

```json
{"handlerFqcn":"App\\TaskHandlers\\ProcessPayoutHandler","handlerFile":"lib/TaskHandlers/ProcessPayoutHandler.php","taskClass":"App\\Tasks\\ProcessPayout","taskId":"process_payout","taskFile":"lib/Tasks/ProcessPayout.php"}
```

---

### mutations.jsonl

Mutation handlers with their registered actions and adapter information.

**Key fields:** `fqcn`, `file`, `actions`, `usesAdapter`, `adapterClass`

```json
{"fqcn":"App\\Mutations\\PayoutMutator","file":"lib/Mutations/PayoutMutator.php","actions":["create","update"],"usesAdapter":true,"adapterClass":"App\\Adapters\\PayoutMutationAdapter"}
```

---

### dependency-map.jsonl

The forward dependency graph. For each class, lists everything it depends on as outbound edges.

**Key fields:** `fqcn`, `file`, `edges` (array of `{type, target, via?}`)

```json
{"fqcn":"App\\Services\\PayoutService","file":"lib/Services/PayoutService.php","edges":[{"type":"injects","target":"App\\Datastores\\Interfaces\\PayoutDatastoreInterface"},{"type":"implements","target":"App\\Contracts\\PayoutServiceInterface"}]}
```

This file answers the question: "What does class X depend on?"

---

### dependents-map.jsonl

The reverse dependency graph. For each class or interface, lists everything that depends on it as inbound edges.

**Key fields:** `fqcn`, `file`, `edges` (array of `{type, source, via?}`)

```json
{"fqcn":"App\\Contracts\\PayoutServiceInterface","file":null,"edges":[{"type":"implemented-by","source":"App\\Services\\PayoutService"},{"type":"injected-by","source":"App\\Controllers\\GetPayoutsController"},{"type":"injected-by","source":"App\\Controllers\\CreatePayoutController"}]}
```

This file answers the question: "What depends on class X?"

Note that the `file` field is null for interfaces and classes that exist only in vendor packages.

---

### orphans.jsonl

Classes that have no edges in either direction. They neither depend on anything in the dependency graph nor are depended upon by anything else.

**Key fields:** `fqcn`, `file`

```json
{"fqcn":"App\\Helpers\\LegacyFormatter","file":"lib/Helpers/LegacyFormatter.php"}
```

Orphans are candidates for removal. They may be dead code, or they may be used through mechanisms the indexer does not track (like reflection or dynamic instantiation).

---

## Dependency graph edge types

The dependency graph uses 9 edge types for the forward direction (`dependency-map.jsonl`) and 9 corresponding inverted types for the reverse direction (`dependents-map.jsonl`).

### Forward edges (dependency-map)

| Edge type | Meaning | Example |
|-----------|---------|---------|
| `injects` | Constructor parameter type hint | `PayoutService` injects `PayoutDatastoreInterface` |
| `implements` | Interface implementation | `PayoutService` implements `PayoutServiceInterface` |
| `extends` | Class inheritance | `AdminPayoutService` extends `PayoutService` |
| `uses-trait` | Trait usage | `PayoutDatastore` uses-trait `WithDatastoreDecorator` |
| `listens-to` | Event listener registration | `CalculatePayoutListener` listens-to `SaleTriggered` |
| `handles-task` | Task handler registration | `ProcessPayoutHandler` handles-task `ProcessPayout` |
| `proxies` | Facade proxy | `Transactions` proxies `TransactionServiceInterface` |
| `resolves-to` | DI binding resolution | `PayoutServiceInterface` resolves-to `PayoutService` |
| `mutates-via` | Mutation adapter | `PayoutMutator` mutates-via `PayoutMutationAdapter` |

### Inverted edges (dependents-map)

| Edge type | Meaning | Forward counterpart |
|-----------|---------|---------------------|
| `injected-by` | Something injects this type | `injects` |
| `implemented-by` | A class implements this interface | `implements` |
| `extended-by` | A class extends this class | `extends` |
| `trait-used-by` | A class uses this trait | `uses-trait` |
| `listened-by` | A listener is registered for this event | `listens-to` |
| `task-handled-by` | A handler is registered for this task | `handles-task` |
| `proxied-by` | A facade proxies this interface | `proxies` |
| `resolved-from` | A concrete resolves from this abstract | `resolves-to` |
| `adapter-for` | An adapter is used by this mutator | `mutates-via` |

### The via field

Some edges include an optional `via` field that records where the relationship is established. For `resolves-to` edges, the `via` field contains the FQCN of the initializer or application that registered the binding.

```json
{"type":"resolves-to","target":"App\\Services\\PayoutService","via":"App\\Initializers\\PayoutInitializer"}
```

---

## Querying patterns

Because each file is JSONL, you can query the index with standard Unix tools. No special tooling required.

### Find everything that depends on an interface

```bash
grep "PayoutDatastoreInterface" .phpnomad/dependents-map.jsonl
```

This returns a single JSON line listing every class that injects, implements, or otherwise references the interface.

### Find what a class depends on

```bash
grep "PayoutService" .phpnomad/dependency-map.jsonl
```

Returns the class's outbound edges, showing all its constructor dependencies, implemented interfaces, traits, and other relationships.

### List all unreferenced classes

```bash
cat .phpnomad/orphans.jsonl | jq '.fqcn'
```

Returns the FQCN of every class with no relationships in the dependency graph.

### Find all GET endpoints

```bash
grep '"method":"GET"' .phpnomad/controllers.jsonl
```

### Find listeners for a specific event

```bash
grep "SaleTriggered" .phpnomad/initializers.jsonl
```

### Find a table's column schema

```bash
grep '"tableName":"payouts"' .phpnomad/tables.jsonl
```

### Find what interface a facade proxies

```bash
grep "Transactions" .phpnomad/facades.jsonl
```

### Pipe into jq for structured output

```bash
grep "PayoutService" .phpnomad/dependency-map.jsonl | jq '.edges[] | select(.type == "injects") | .target'
```

This extracts just the constructor dependencies for `PayoutService`.

---

## Token efficiency

The index is designed for AI agent consumption, where every token costs money and time. Here are real measurements from a project with 1,019 classes.

| Query | Index size | Raw source size | Savings |
|-------|-----------|----------------|---------|
| Reverse dependency lookup on EventStrategy | 7 KB | 394 KB | 54x |
| Full boot sequence | 10 KB | 315 KB | 32x |
| All task handlers | 0.3 KB | 37 KB | 109x |

The savings come from two factors. First, the index stores only the structural information that tools and agents need, not method bodies, comments, or whitespace. Second, JSONL lets you query a single line instead of reading the entire file.

For an AI agent that needs to understand how an interface is used across a project, grepping `dependents-map.jsonl` returns one line with all the information. The alternative is reading dozens of source files to manually trace imports and constructor parameters.

---

## Next steps

- **New to the indexer?** Read the [introduction](introduction) for how the indexing pipeline works.
- **Want to run the indexer?** See the [commands reference](../commands/introduction) for `phpnomad index`.
- **Looking for the CLI overview?** Check the [CLI package introduction](../introduction).
