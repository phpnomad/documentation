---
id: cli-commands-introduction
slug: docs/packages/cli/commands/introduction
title: Command Reference
doc_type: reference
status: active
language: en
owner: docs-team
last_reviewed: 2026-04-13
summary: Reference for all five CLI commands, covering syntax, options, and example output for index, inspect:di, inspect:routes, context, and make.
llm_summary: >
  Complete command reference for the phpnomad CLI. Documents the five commands (index, inspect:di,
  inspect:routes, context, make) with full option descriptions, usage examples, and sample terminal
  output. The index command builds a JSONL project index. inspect:di renders the DI boot sequence as a
  tree. inspect:routes lists REST routes grouped by HTTP method with capability badges. context generates
  a compact markdown summary for AI agents. make scaffolds PHP files from recipe specs with preflight
  validation and auto-registration.
questions_answered:
  - How do I run the phpnomad indexer?
  - What options does phpnomad index accept?
  - How do I view the boot sequence and DI bindings?
  - What does inspect:di output look like?
  - How do I list REST routes in a PHPNomad project?
  - What are the capability badges in inspect:routes?
  - How do I generate a project context for AI agents?
  - What sections can I include in the context output?
  - How do I scaffold a new listener or controller?
  - What happens when make validation fails?
  - What built-in recipes are available?
  - How do I use a custom recipe file with make?
audience:
  - developers
  - ai-agent-authors
  - devops
tags:
  - cli
  - commands
  - reference
  - indexer
  - scaffolder
  - static-analysis
llm_tags:
  - cli-commands
  - phpnomad-index
  - inspect-di
  - inspect-routes
  - context-generation
  - scaffolding
keywords:
  - phpnomad index
  - phpnomad inspect:di
  - phpnomad inspect:routes
  - phpnomad context
  - phpnomad make
  - cli commands
  - boot sequence
  - route inspection
  - code scaffolding
related:
  - ../introduction
  - ../indexer/introduction
  - ../indexer/output-format
  - ../scaffolder/introduction
  - ../scaffolder/built-in-recipes
see_also:
  - ../introduction
  - ../indexer/introduction
  - ../scaffolder/introduction
noindex: false
---

# Command Reference

The PHPNomad CLI ships five commands. All of them operate on a project directory that you point to with `--path`. If you omit `--path`, the CLI defaults to the current working directory.

```
phpnomad <command> [options]
```

---

## phpnomad index

Scans a PHPNomad project using AST analysis and builds the full JSONL index inside the `.phpnomad/` directory. This is the foundation that the other commands depend on. Running `index` explicitly is optional because `inspect:di`, `inspect:routes`, and `context` will build the index automatically when one does not already exist.

### Syntax

```bash
phpnomad index [--path=<dir>]
```

### Options

| Option | Default | Description |
|--------|---------|-------------|
| `--path` | `./` | Path to the target project directory. |

### Example

```bash
phpnomad index --path=/var/www/my-app
```

### Example output

```
Index written to /var/www/my-app/.phpnomad/
  meta.json, classes.jsonl, initializers.jsonl, applications.jsonl,
  controllers.jsonl, commands.jsonl, dependencies.jsonl,
  tables.jsonl, events.jsonl, graphql-types.jsonl,
  facades.jsonl, task-handlers.jsonl, mutations.jsonl,
  dependency-map.jsonl, dependents-map.jsonl, orphans.jsonl,
  phpnomad-cli.md

Summary
  Applications:   4
  Initializers:   74
  Bindings:       244
  Controllers:    110
  Commands:       23
  Tables:         39
  Events:         82
  Listeners:      151
  GraphQL types:  0
  Facades:        29
  Task handlers:  1
  Mutations:      0
  Dependencies:   186
  Dep map:        487
  Dependents map: 392
  Orphans:        50
  Classes:        1019
```

The command produces 16 JSONL files plus a `meta.json` and a `phpnomad-cli.md` context file. See the [output format reference](../indexer/output-format) for details on each file.

---

## phpnomad inspect:di

Displays the boot sequence and DI bindings in a human-readable tree. The tree walks through each application class, its pre-bootstrap bindings, the numbered boot sequence of initializers, and the post-bootstrap bindings. This is the fastest way to understand how a project wires itself together at startup.

### Syntax

```bash
phpnomad inspect:di [--path=<dir>] [--format=<format>] [--fresh]
```

### Options

| Option | Default | Description |
|--------|---------|-------------|
| `--path` | `./` | Path to the target project directory. |
| `--format` | `table` | Output format. `table` renders the human-readable tree. `json` dumps the raw index as JSON. |
| `--fresh` | off | Force a re-index instead of reading the cached `.phpnomad/` data. |

### Examples

```bash
# Tree view from the current directory
phpnomad inspect:di

# JSON output for a specific project
phpnomad inspect:di --path=/var/www/my-app --format=json

# Force re-index before displaying
phpnomad inspect:di --path=/var/www/my-app --fresh
```

### Example output (table format)

```
Application: App\MyApp (/var/www/my-app/lib/MyApp.php)

Pre-bootstrap bindings:
  [bind] Strategies\MySqlStrategy -> Interfaces\DatabaseStrategy
  [factory] Strategies\RedisCache -> Interfaces\CacheStrategy

Boot sequence (12 initializers, bootstrap()):
  #1   CoreInit                                         3 bindings, 2 listeners
  #2   AuthInit                                         5 bindings
  #3   DatabaseInit                                     4 bindings, 1 listener
  #4   RestInit                                         8 bindings
  #5   --- $this->loadModules() (dynamic) ---
  #6   EventInit                                        2 bindings, 6 listeners
  #7   CacheInit (vendor)                               1 binding
  #8   MailInit                                         2 bindings
  #9   TaskInit                                         1 binding, 1 listener
  #10  GraphQLInit                                      3 bindings
  #11  AdminInit                                        6 bindings, 4 listeners
  #12  CronInit                                         1 binding

Post-bootstrap bindings:
  [factory] Factories\AppConfigFactory -> Interfaces\ConfigProvider

Summary
  1 application(s)
  12 initializers
  36 bindings
  22 controllers
  5 commands
  14 listeners
  8 tables
  12 events
  4 facades
  1 task handlers
  0 mutations
  18 dependency trees
```

Initializers loaded from vendor packages are tagged `(vendor)`. Dynamic boot calls (like iterating over a module list) appear as `--- source (dynamic) ---` because the CLI cannot statically resolve them.

---

## phpnomad inspect:routes

Lists all REST routes in the project, grouped by HTTP method. Each route shows its endpoint path, the controller FQCN (shortened to the last three namespace segments), and capability badges indicating which cross-cutting concerns are attached.

### Syntax

```bash
phpnomad inspect:routes [--path=<dir>] [--format=<format>] [--fresh]
```

### Options

| Option | Default | Description |
|--------|---------|-------------|
| `--path` | `./` | Path to the target project directory. |
| `--format` | `table` | Output format. `table` renders grouped routes. `json` dumps the raw index as JSON. |
| `--fresh` | off | Force a re-index instead of reading the cached `.phpnomad/` data. |

### Examples

```bash
# Table view from the current directory
phpnomad inspect:routes

# JSON output for a specific project
phpnomad inspect:routes --path=/var/www/my-app --format=json
```

### Example output (table format)

```
GET (5)
  /api/users                              Controllers\ListUsersController [middleware, validations]
  /api/users/(?P<id>\d+)                  Controllers\GetUserController [middleware]
  {base}/settings                         Controllers\GetSettingsController [middleware, validations]
  {base}/dashboard                        Controllers\DashboardController
  {base}/health                           Controllers\HealthCheckController

POST (3)
  /api/users                              Controllers\CreateUserController [middleware, validations, interceptors]
  /api/auth/login                         Controllers\LoginController [validations]
  {base}/webhooks                         Controllers\WebhookController [middleware, interceptors]

DELETE (1)
  /api/users/(?P<id>\d+)                  Controllers\DeleteUserController [middleware, validations]

Summary
  9 controller(s)
  6 with middleware
  5 with validations
  2 with interceptors
  3 using WithEndpointBase
```

### Capability badges

| Badge | Meaning |
|-------|---------|
| `middleware` | Controller has middleware attached (auth, pagination, etc.). |
| `validations` | Controller defines input validation rules. |
| `interceptors` | Controller has post-response interceptors (events, logging, etc.). |

### The {base} prefix

Controllers that use the `WithEndpointBase` trait show `{base}` as the first segment of their path. This prefix is resolved at runtime from configuration, so the CLI cannot determine the actual value statically. The summary line reports how many controllers use this pattern.

---

## phpnomad context

Generates a compact project context document as markdown. This is designed for AI agents that need a quick structural overview of a project without reading every source file. The output covers routes, tables, events, facades, commands, task handlers, bindings, GraphQL types, and mutations.

### Syntax

```bash
phpnomad context [--path=<dir>] [--fresh] [--sections=<list>] [--output=<dest>]
```

### Options

| Option | Default | Description |
|--------|---------|-------------|
| `--path` | `./` | Path to the target project directory. |
| `--fresh` | off | Force a re-index instead of reading the cached `.phpnomad/` data. |
| `--sections` | all | Comma-separated list of sections to include. Available sections: `routes`, `tables`, `events`, `facades`, `commands`, `tasks`, `bindings`, `graphql`, `mutations`. |
| `--output` | `stdout` | Output destination. `stdout` prints to the terminal. `file` writes to `.phpnomad/context.md`. |

### Examples

```bash
# Full context to stdout
phpnomad context --path=/var/www/my-app

# Only routes and tables, written to a file
phpnomad context --path=/var/www/my-app --sections=routes,tables --output=file

# Just events and bindings
phpnomad context --path=/var/www/my-app --sections=events,bindings
```

### Example output (partial)

```markdown
# Project Context
path: /var/www/my-app
indexed: 2026-04-13T10:30:00+00:00
routes: 22 | tables: 8 | events: 12 | commands: 5 | facades: 4 | bindings: 36

## Routes

### GET (5)
/api/users                              Controllers\ListUsersController [middleware, validations]
/api/users/(?P<id>\d+)                  Controllers\GetUserController [middleware]
{base}/settings                         Controllers\GetSettingsController [middleware]

### POST (3)
/api/users                              Controllers\CreateUserController [middleware, validations, interceptors]

## Tables

users: id(bigint PK), email(varchar[255]), name(varchar[100]), created_at(datetime), updated_at(datetime)
posts: id(bigint PK), user_id(bigint FK), title(varchar[255]), body(text), status(varchar[20])

## Events

user.created (Events\UserCreated): userId(int), email(string)
  -> Listeners\SendWelcomeEmail (via AppInit)
  -> Listeners\CreateDefaultSettings (via AppInit)
```

When `--output=file` is used, the command writes to `.phpnomad/context.md` and prints a confirmation message instead of the content itself.

---

## phpnomad make

Generates PHP files from a recipe specification and auto-registers the new classes in the appropriate initializer. Recipes define what files to create, what template to use, and where to register the result. The command runs preflight validation before writing anything, so it will catch missing variables and other problems up front.

### Syntax

```bash
phpnomad make --from=<recipe> [--path=<dir>] [<vars>]
```

### Options

| Option | Default | Description |
|--------|---------|-------------|
| `--from` | required | Recipe name (for built-in recipes) or path to a custom JSON recipe file. |
| `--path` | `./` | Path to the target project directory. |

The positional `<vars>` argument is a JSON object containing the variable values that the recipe requires. Each recipe defines its own set of variables.

### Built-in recipes

The CLI ships four built-in recipes:

| Recipe | Purpose |
|--------|---------|
| `listener` | Creates an event listener class and registers it in an initializer. |
| `event` | Creates an event class. |
| `command` | Creates a console command class and registers it. |
| `controller` | Creates a REST controller class and registers it. |

See the [built-in recipes reference](../scaffolder/built-in-recipes) for the full variable definitions and templates for each recipe.

### Examples

```bash
# Create a listener from the built-in recipe
phpnomad make --from=listener '{"name":"SendWelcomeEmail","event":"App\\Events\\UserCreated","initializer":"App\\AppInit"}'

# Create a controller
phpnomad make --from=controller '{"name":"ListPayouts","initializer":"App\\RestInit"}'

# Use a custom recipe file
phpnomad make --from=./recipes/feature.json '{"name":"Payout"}'
```

### Example output (success)

```
Recipe: listener
  Create an event listener class

  Created: lib/Listeners/SendWelcomeEmail.php
  Registered: registerListeners() in App\AppInit

Done: 1 file(s) created, 1 registration(s) performed.
```

### Example output (validation failure)

If required variables are missing or other preflight checks fail, the command reports the errors and exits without writing any files.

```
Recipe: listener
  Create an event listener class

Preflight validation failed:
  - Missing required variable: name (Listener class name)
  - Missing required variable: event (FQCN of the event class)
```

Other validation checks include verifying that target files do not already exist, that the specified initializer file can be found on disk, and that any required DI bindings are present in the project index.

### Custom recipes

You can point `--from` at any JSON file to use a custom recipe. The recipe format defines variables, file templates, and registration instructions. See the [scaffolder introduction](../scaffolder/introduction) for the full recipe specification.
