# GraphQL

`phpnomad/graphql` is PHPNomad's **abstract GraphQL layer**. It defines the contracts for building a GraphQL API — type
definitions, field resolvers, execution, and schema composition — in a way that's **agnostic to any particular GraphQL
engine**.

The package contains only interfaces and a small exception class. Concrete execution is provided by a separate
integration package (e.g. [phpnomad/webonyx-integration](/packages/graphql/webonyx-integration)). This separation means
you can swap engines without touching your type definitions or resolvers.

## Key ideas at a glance

* **TypeDefinition** — one unit of schema: SDL string plus a resolver map for any fields with custom logic.
* **FieldResolver** — a single-responsibility class that resolves one field.
* **ResolverContext** — carries the `Request` through the execution so resolvers can read auth, headers, etc.
* **GraphQLStrategy** — the execution engine contract; accepts type definitions and runs queries.
* **HasTypeDefinitions** — the initializer interface that wires your type definitions into the bootstrapper.

---

## The Execution Flow

When a GraphQL query enters a system wired with `phpnomad/graphql`:

```
Request → GraphQLStrategy::execute() → Schema (built from TypeDefinitions) → FieldResolvers → array result
```

### TypeDefinitions registered at boot

During application boot, initializers implementing `HasTypeDefinitions` push SDL + resolver maps into the
`GraphQLStrategy`. The strategy accumulates these and builds the final schema lazily on first execution.

### execute()

`GraphQLStrategy::execute()` accepts a query string, a variables map, and a `ResolverContext`. It returns a plain PHP
array matching the GraphQL wire format (`['data' => [...]]` or `['errors' => [...]]`).

### FieldResolvers

For each field that has custom logic, a `FieldResolver` class is registered in the `TypeDefinition`'s resolver map. The
strategy resolves these from the DI container at query time. Fields with no entry use the engine's default property
resolution (reading `$rootValue['fieldName']` or `$rootValue->fieldName`).

---

## Packages

| Package | Purpose |
|---------|---------|
| `phpnomad/graphql` | Interfaces only — portable, no engine dependency |
| `phpnomad/webonyx-integration` | Concrete engine using `webonyx/graphql-php` |

## Installation

```bash
composer require phpnomad/graphql
```

For the concrete engine:

```bash
composer require phpnomad/webonyx-integration
```
