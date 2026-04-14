---
id: cli-scaffolder-built-in-recipes
slug: docs/packages/cli/scaffolder/built-in-recipes
title: Built-in Recipes
doc_type: reference
status: active
language: en
owner: docs-team
last_reviewed: 2026-04-13
summary: Reference for all built-in scaffolder recipes, including generated output, required variables, auto-registration behavior, and recipe stacking.
llm_summary: >
  Documents all 16 built-in recipes bundled with the PHPNomad CLI scaffolder: listener, event,
  command, controller, facade, task, task-handler, mutation, table, graphql-type, initializer,
  model, model-adapter, datastore, database-handler, and database-datastore. Each recipe section
  covers what the recipe creates, what variables it requires, what auto-registration it performs
  in initializer files (including the Has* interface, method name, and registration type), and a
  complete command example. Also covers recipe stacking, where composite recipes reference other
  recipes to scaffold entire features from a single command. Includes the three auto-registration
  scenarios: appending to an existing method, creating the method from scratch (with interface and
  use statement), and duplicate detection that prevents double-registration.
questions_answered:
  - What built-in recipes does the PHPNomad scaffolder include?
  - How do I scaffold a listener with the PHPNomad CLI?
  - How do I scaffold an event with the PHPNomad CLI?
  - How do I scaffold a CLI command with the PHPNomad CLI?
  - How do I scaffold a REST controller with the PHPNomad CLI?
  - How do I scaffold a facade with the PHPNomad CLI?
  - How do I scaffold a task and task handler with the PHPNomad CLI?
  - How do I scaffold a mutation handler with the PHPNomad CLI?
  - How do I scaffold a database table with the PHPNomad CLI?
  - How do I scaffold a GraphQL type with the PHPNomad CLI?
  - How do I scaffold a datastore with the PHPNomad CLI?
  - How do I scaffold a full database-backed datastore with the PHPNomad CLI?
  - What is recipe stacking?
  - How do composite recipes work?
  - What variables does each built-in recipe require?
  - What PHP code does each recipe generate?
  - How does auto-registration modify my initializer file?
  - What happens if the initializer method does not exist yet?
  - How does the scaffolder prevent duplicate registrations?
  - What interfaces does auto-registration add to my initializer?
audience:
  - developers
  - backend engineers
tags:
  - cli
  - scaffolder
  - code-generation
  - recipes
  - reference
  - recipe-stacking
llm_tags:
  - scaffolder-recipes
  - listener-recipe
  - event-recipe
  - command-recipe
  - controller-recipe
  - facade-recipe
  - task-recipe
  - mutation-recipe
  - datastore-recipe
  - database-datastore-recipe
  - recipe-stacking
  - auto-registration
keywords:
  - phpnomad make
  - built-in recipes
  - listener scaffolding
  - event scaffolding
  - command scaffolding
  - controller scaffolding
  - facade scaffolding
  - task scaffolding
  - mutation scaffolding
  - datastore scaffolding
  - database-datastore scaffolding
  - recipe stacking
  - composite recipes
  - auto-registration
  - HasListeners
  - HasCommands
  - HasControllers
  - HasFacades
  - HasTaskHandlers
  - HasMutations
  - HasTypeDefinitions
  - HasClassDefinitions
related:
  - introduction
  - recipe-spec
  - ../commands/introduction
see_also:
  - introduction
  - recipe-spec
noindex: false
---

# Built-in Recipes

The PHPNomad CLI ships with 16 built-in recipes covering common scaffolding tasks. These range from single-file recipes like listeners and facades, to multi-file recipes like datastores, to composite recipes like `database-datastore` that stack multiple recipes together to scaffold entire features from one command.

Built-in recipes are referenced by name. When you pass `--from=listener`, the scaffolder looks for `listener.json` in its bundled `Recipes/` directory. You do not need a file path for these.

The recipes are organized into three categories:

- **Single-artifact recipes** create one PHP file each: listener, event, command, controller, facade, task, task-handler, mutation, table, graphql-type, initializer, model, model-adapter.
- **Multi-file recipes** create several related files: datastore, database-handler.
- **Composite recipes** stack other recipes together: database-datastore.

---

## listener

Creates an event listener class that implements `CanHandle` and registers it in an initializer's `getListeners()` method.

### Required variables

| Variable | Type | Description |
|----------|------|-------------|
| `name` | string | Listener class name (e.g. `SendWelcomeEmail`) |
| `event` | string | FQCN of the event class to listen to |
| `initializer` | string | FQCN of the initializer to register in |

### Command

```bash
phpnomad make --from=listener '{"name":"SendWelcomeEmail","event":"App\\Events\\UserCreated","initializer":"App\\AppInit"}'
```

### Generated file

Output path: `lib/Listeners/SendWelcomeEmail.php`

```php
<?php

namespace App\Listeners;

use PHPNomad\Events\Interfaces\CanHandle;
use PHPNomad\Events\Interfaces\Event;
use App\Events\UserCreated;

class SendWelcomeEmail implements CanHandle
{
    public function __construct(
        // TODO: Add constructor dependencies
    ) {
    }

    public function handle(Event $event): void
    {
        // TODO: Implement listener logic
    }
}
```

The `{{namespace}}` token is resolved automatically from the output path and your project's PSR-4 config. The `{{event}}` token produces the use statement for the event class, so the event's short name is available in the file.

### Registration

The scaffolder opens the initializer class (e.g. `App\AppInit`) and adds a map entry to `getListeners()`:

```php
UserCreated::class => SendWelcomeEmail::class
```

The registration type is `map`, meaning the event class is the key and the listener class is the value. If the event key already exists with a single listener, the mutator wraps the existing value and the new one into an array, matching PHPNomad's convention for multiple listeners on the same event.

The registration targets the `HasListeners` interface (`PHPNomad\Events\Interfaces\HasListeners`). If the initializer does not yet implement `HasListeners`, the scaffolder adds it automatically.

### Output

```
Recipe: listener
  Creates an event listener and registers it in an initializer
  Created: lib/Listeners/SendWelcomeEmail.php
  Registered: getListeners() in App\AppInit

Done: 1 file(s) created, 1 registration(s) performed.
```

---

## event

Creates an event class that implements `Event` with a static `getId()` method. Events do not require registration because they are referenced directly by listeners.

### Required variables

| Variable | Type | Description |
|----------|------|-------------|
| `name` | string | Event class name (e.g. `UserCreated`) |
| `eventId` | string | Unique event identifier (e.g. `user.created`) |

### Command

```bash
phpnomad make --from=event '{"name":"UserCreated","eventId":"user.created"}'
```

### Generated file

Output path: `lib/Events/UserCreated.php`

```php
<?php

namespace App\Events;

use PHPNomad\Events\Interfaces\Event;

class UserCreated implements Event
{
    public function __construct(
        // TODO: Add event properties
    ) {
    }

    public static function getId(): string
    {
        return 'user.created';
    }
}
```

The constructor is left as a TODO. Events in PHPNomad typically carry data as public readonly properties passed through the constructor, so you fill this in with the fields your listeners need.

### Registration

None. Events are passive data carriers. They get referenced by class name in listener registrations and dispatched through the event system at runtime.

### Output

```
Recipe: event
  Creates an event class with a unique ID
  Created: lib/Events/UserCreated.php

Done: 1 file(s) created, 0 registration(s) performed.
```

---

## command

Creates a CLI command class that implements `Command` and registers it in an initializer's `getCommands()` method.

### Required variables

| Variable | Type | Description |
|----------|------|-------------|
| `name` | string | Command class name (e.g. `DeployCommand`) |
| `signature` | string | Command signature string (e.g. `deploy {env:Target environment}`) |
| `description` | string | Short description for help output |
| `initializer` | string | FQCN of the initializer to register in |

### Command

```bash
phpnomad make --from=command '{"name":"DeployCommand","signature":"deploy {env:Target environment} {--force:Force deployment}","description":"Deploy to environment","initializer":"App\\AppInit"}'
```

### Generated file

Output path: `lib/Commands/DeployCommand.php`

```php
<?php

namespace App\Commands;

use PHPNomad\Console\Interfaces\Command;
use PHPNomad\Console\Interfaces\Input;
use PHPNomad\Console\Interfaces\OutputStrategy;

class DeployCommand implements Command
{
    public function __construct(
        protected OutputStrategy $output
    ) {
    }

    public function getSignature(): string
    {
        return 'deploy {env:Target environment} {--force:Force deployment}';
    }

    public function getDescription(): string
    {
        return 'Deploy to environment';
    }

    public function handle(Input $input): int
    {
        // TODO: Implement command logic

        return 0;
    }
}
```

The generated command class injects `OutputStrategy` through the constructor, giving you access to `$this->output` for writing to the console. The `handle()` method receives an `Input` instance you can use to read arguments and options parsed from the signature.

### Registration

The scaffolder opens the initializer class and adds a list entry to `getCommands()`:

```php
DeployCommand::class
```

The registration type is `list`, meaning the command class is appended as a simple array item. The registration targets the `HasCommands` interface (`PHPNomad\Console\Interfaces\HasCommands`). If the initializer does not yet implement `HasCommands`, the scaffolder adds it automatically.

### Output

```
Recipe: command
  Creates a CLI command and registers it in an initializer
  Created: lib/Commands/DeployCommand.php
  Registered: getCommands() in App\AppInit

Done: 1 file(s) created, 1 registration(s) performed.
```

---

## controller

Creates a REST controller class and registers it in an initializer's `getControllers()` method.

### Required variables

| Variable | Type | Description |
|----------|------|-------------|
| `name` | string | Controller class name (e.g. `GetUsers`) |
| `method` | string | HTTP method (`GET`, `POST`, `PUT`, `DELETE`, `PATCH`) |
| `endpoint` | string | Endpoint path (e.g. `/users`) |
| `initializer` | string | FQCN of the initializer to register in |

### Command

```bash
phpnomad make --from=controller '{"name":"GetUsers","method":"GET","endpoint":"/users","initializer":"App\\AppInit"}'
```

### Generated file

Output path: `lib/Rest/GetUsers.php`

```php
<?php

namespace App\Rest;

class GetUsers
{
    public function __construct(
        // TODO: Add constructor dependencies
    ) {
    }

    public function getMethod(): string
    {
        return 'GET';
    }

    public function getEndpoint(): string
    {
        return '/users';
    }

    public function handle()
    {
        // TODO: Implement controller logic
    }
}
```

The generated controller is a lightweight skeleton. Unlike the full `Controller` interface from `phpnomad/rest` (which uses `Request`/`Response` contracts and the `Method` enum), the scaffolded controller provides the basic structure with plain string returns for `getMethod()` and `getEndpoint()`. You can refine the class to implement the full `Controller` interface and inject `Response` as needed for your project.

### Registration

The scaffolder opens the initializer class and adds a list entry to `getControllers()`:

```php
GetUsers::class
```

The registration type is `list`. The registration targets the `HasControllers` interface (`PHPNomad\Rest\Interfaces\HasControllers`). If the initializer does not yet implement `HasControllers`, the scaffolder adds it automatically.

### Output

```
Recipe: controller
  Creates a REST controller and registers it in an initializer
  Created: lib/Rest/GetUsers.php
  Registered: getControllers() in App\AppInit

Done: 1 file(s) created, 1 registration(s) performed.
```

---

## facade

Creates a facade class that extends `PHPNomad\Facade\Abstracts\Facade` and registers it in an initializer's `getFacades()` method. Facades provide a static interface to services resolved from the container.

### Required variables

| Variable | Type | Description |
|----------|------|-------------|
| `name` | string | Facade class name (e.g. `PaymentFacade`) |
| `interface` | string | FQCN of the interface to proxy |
| `initializer` | string | FQCN of the initializer to register in |

### Command

```bash
phpnomad make --from=facade '{"name":"PaymentFacade","interface":"App\\Services\\PaymentService","initializer":"App\\AppInit"}'
```

### Generated file

Output path: `lib/Facades/PaymentFacade.php`

```php
<?php

namespace App\Facades;

use PHPNomad\Facade\Abstracts\Facade;
use App\Services\PaymentService;

class PaymentFacade extends Facade
{
    protected static function getAbstraction(): string
    {
        return PaymentService::class;
    }
}
```

The `{{interface}}` variable produces a use statement and is referenced by its short name in the `getAbstraction()` method.

### Registration

The scaffolder opens the initializer class and adds a list entry to `getFacades()`:

```php
PaymentFacade::class
```

The registration type is `list`. The registration targets the `HasFacades` interface. If the initializer does not yet implement `HasFacades`, the scaffolder adds it automatically.

---

## task

Creates a task class implementing `Task` with `getId()`, `toPayload()`, and `fromPayload()` methods. Tasks are data objects dispatched through the task system. They do not require registration.

### Required variables

| Variable | Type | Description |
|----------|------|-------------|
| `name` | string | Task class name (e.g. `SendEmailTask`) |
| `taskId` | string | Unique task identifier (e.g. `send.email`) |

### Command

```bash
phpnomad make --from=task '{"name":"SendEmailTask","taskId":"send.email"}'
```

### Registration

None. Tasks are referenced by class name when dispatched and when registering their handlers.

---

## task-handler

Creates a handler class implementing `CanHandleTask` and registers it in an initializer's `getTaskHandlers()` method.

### Required variables

| Variable | Type | Description |
|----------|------|-------------|
| `name` | string | Handler class name (e.g. `HandleSendEmail`) |
| `task` | string | FQCN of the task class to handle |
| `initializer` | string | FQCN of the initializer to register in |

### Command

```bash
phpnomad make --from=task-handler '{"name":"HandleSendEmail","task":"App\\Tasks\\SendEmailTask","initializer":"App\\AppInit"}'
```

### Registration

The scaffolder opens the initializer class and adds a map entry to `getTaskHandlers()`:

```php
SendEmailTask::class => HandleSendEmail::class
```

The registration type is `map`, with the task class as the key and the handler class as the value. The registration targets the `HasTaskHandlers` interface. If the initializer does not yet implement `HasTaskHandlers`, the scaffolder adds it automatically.

---

## mutation

Creates a mutation handler class that uses the `CanMutateFromAdapter` trait and registers it in an initializer's `getMutations()` method. Mutations handle data transformations identified by a plain string action key rather than a class reference.

### Required variables

| Variable | Type | Description |
|----------|------|-------------|
| `name` | string | Mutation class name (e.g. `CreateUserMutation`) |
| `action` | string | Action string key (e.g. `create_user`) |
| `initializer` | string | FQCN of the initializer to register in |

### Command

```bash
phpnomad make --from=mutation '{"name":"CreateUserMutation","action":"create_user","initializer":"App\\AppInit"}'
```

### Registration

The scaffolder opens the initializer class and adds a map entry to `getMutations()`:

```php
'create_user' => CreateUserMutation::class
```

The registration type is `map`, but the key is a plain string (the action) rather than a `::class` reference. The registration targets the `HasMutations` interface. If the initializer does not yet implement `HasMutations`, the scaffolder adds it automatically.

---

## table

Creates a table class extending `Table` with `getUnprefixedName()` and `getColumns()` methods. Tables define the schema for database-backed datastores. They do not require registration.

### Required variables

| Variable | Type | Description |
|----------|------|-------------|
| `name` | string | Table class name (e.g. `PayoutsTable`) |
| `tableName` | string | Database table name (e.g. `payouts`) |

### Command

```bash
phpnomad make --from=table '{"name":"PayoutsTable","tableName":"payouts"}'
```

### Registration

None. Table classes are referenced directly by database handlers that use them.

---

## graphql-type

Creates a GraphQL type definition class implementing `TypeDefinition` with `getSdl()` and `getResolvers()` methods, then registers it in an initializer's `getTypeDefinitions()` method.

### Required variables

| Variable | Type | Description |
|----------|------|-------------|
| `name` | string | Type definition class name (e.g. `UserType`) |
| `typeName` | string | GraphQL type name as it appears in the schema (e.g. `User`) |
| `initializer` | string | FQCN of the initializer to register in |

### Command

```bash
phpnomad make --from=graphql-type '{"name":"UserType","typeName":"User","initializer":"App\\AppInit"}'
```

### Registration

The scaffolder opens the initializer class and adds a list entry to `getTypeDefinitions()`:

```php
UserType::class
```

The registration type is `list`. The registration targets the `HasTypeDefinitions` interface. If the initializer does not yet implement `HasTypeDefinitions`, the scaffolder adds it automatically.

---

## initializer

Creates an initializer class that uses `HasClassDefinitions` and `CanSetContainer`. Initializers are the central wiring point for PHPNomad applications. This recipe creates the class itself. It does not register anything, because initializers are typically referenced from the application bootstrap, not from other initializers.

### Required variables

| Variable | Type | Description |
|----------|------|-------------|
| `name` | string | Initializer class name (e.g. `AppInit`) |

### Command

```bash
phpnomad make --from=initializer '{"name":"AppInit"}'
```

### Registration

None. Initializers are loaded by the application bootstrap, not registered in other initializers.

---

## model

Creates a data model class that uses the `HasSingleIntIdentity` trait. Models are plain data objects that represent a single record from a datastore. They do not require registration.

### Required variables

| Variable | Type | Description |
|----------|------|-------------|
| `name` | string | Model class name (e.g. `Payout`) |

### Command

```bash
phpnomad make --from=model '{"name":"Payout"}'
```

### Registration

None. Models are referenced by model adapters and datastores that work with them.

---

## model-adapter

Creates a model adapter class with `toModel()` and `toArray()` methods for converting between raw data and model objects. Model adapters do not require registration.

### Required variables

| Variable | Type | Description |
|----------|------|-------------|
| `name` | string | Adapter class name (e.g. `PayoutAdapter`) |
| `model` | string | FQCN of the model class to adapt |

### Command

```bash
phpnomad make --from=model-adapter '{"name":"PayoutAdapter","model":"App\\Models\\Payout"}'
```

### Registration

None. Model adapters are referenced by database handlers and other components that need to convert data.

---

## datastore

Creates three files: a datastore interface, a handler interface, and an implementation class with decorator traits. The implementation is registered in an initializer's `getClassDefinitions()` method, binding the datastore interface to its concrete implementation.

### Required variables

| Variable | Type | Description |
|----------|------|-------------|
| `name` | string | Datastore name in PascalCase (e.g. `Payout`) |
| `initializer` | string | FQCN of the initializer to register in |

### Command

```bash
phpnomad make --from=datastore '{"name":"Payout","initializer":"App\\AppInit"}'
```

### Generated files

The recipe creates three files:

| File | Description |
|------|-------------|
| `lib/Datastores/Payout/PayoutDatastore.php` | Datastore interface |
| `lib/Datastores/Payout/PayoutDatastoreHandler.php` | Handler interface |
| `lib/Datastores/Payout/PayoutDatastoreImpl.php` | Implementation with decorator traits |

```php
<?php

namespace App\Datastores\Payout;

interface PayoutDatastore
{
    // TODO: Define datastore methods
}
```

```php
<?php

namespace App\Datastores\Payout;

interface PayoutDatastoreHandler
{
    // TODO: Define handler methods
}
```

```php
<?php

namespace App\Datastores\Payout;

use PHPNomad\Datastore\Traits\CanDecorate;
use PHPNomad\Datastore\Traits\CanFilter;

class PayoutDatastoreImpl implements PayoutDatastore
{
    use CanDecorate;
    use CanFilter;

    public function __construct(
        protected PayoutDatastoreHandler $handler
    ) {
    }

    // TODO: Implement datastore methods
}
```

### Registration

The scaffolder opens the initializer class and adds a map entry to `getClassDefinitions()`:

```php
PayoutDatastore::class => PayoutDatastoreImpl::class
```

The registration type is `map`. The registration targets the `HasClassDefinitions` interface (`PHPNomad\Di\Interfaces\HasClassDefinitions`). If the initializer does not yet implement `HasClassDefinitions`, the scaffolder adds it automatically.

---

## database-handler

Creates a database-specific handler class that extends `IdentifiableDatabaseDatastoreHandler` and registers it in an initializer's `getClassDefinitions()` method. This recipe is typically used alongside the `datastore` recipe to provide the database implementation for a datastore's handler interface.

### Required variables

| Variable | Type | Description |
|----------|------|-------------|
| `name` | string | Handler class name (e.g. `PayoutDatabaseHandler`) |
| `handlerInterface` | string | FQCN of the handler interface to implement |
| `model` | string | FQCN of the model class |
| `modelAdapter` | string | FQCN of the model adapter class |
| `table` | string | FQCN of the table class |
| `initializer` | string | FQCN of the initializer to register in |

### Command

```bash
phpnomad make --from=database-handler '{"name":"PayoutDatabaseHandler","handlerInterface":"App\\Datastores\\Payout\\PayoutDatastoreHandler","model":"App\\Models\\Payout","modelAdapter":"App\\Adapters\\PayoutAdapter","table":"App\\Tables\\PayoutsTable","initializer":"App\\AppInit"}'
```

### Registration

The scaffolder opens the initializer class and adds a map entry to `getClassDefinitions()`:

```php
PayoutDatastoreHandler::class => PayoutDatabaseHandler::class
```

The registration type is `map`. The registration targets the `HasClassDefinitions` interface. If the initializer does not yet implement `HasClassDefinitions`, the scaffolder adds it automatically.

---

## database-datastore (composite)

This is a composite recipe that uses recipe stacking to scaffold an entire database-backed datastore from a single command. It stacks five recipes together: `model`, `model-adapter`, `table`, `datastore`, and `database-handler`. The result is seven files covering the full stack from model to database handler.

### Required variables

| Variable | Type | Description |
|----------|------|-------------|
| `name` | string | Feature name in PascalCase (e.g. `Payout`) |
| `tableName` | string | Database table name (e.g. `payouts`) |
| `initializer` | string | FQCN of the initializer to register in |

### Command

```bash
phpnomad make --from=database-datastore '{"name":"Payout","tableName":"payouts","initializer":"App\\AppInit"}'
```

### Generated files

The composite recipe creates seven files across its five child recipes:

| File | Source recipe |
|------|-------------|
| `lib/Models/Payout.php` | model |
| `lib/Adapters/PayoutAdapter.php` | model-adapter |
| `lib/Tables/PayoutsTable.php` | table |
| `lib/Datastores/Payout/PayoutDatastore.php` | datastore |
| `lib/Datastores/Payout/PayoutDatastoreHandler.php` | datastore |
| `lib/Datastores/Payout/PayoutDatastoreImpl.php` | datastore |
| `lib/Datastores/Payout/PayoutDatabaseHandler.php` | database-handler |

### Registration

Two registrations are performed in the initializer's `getClassDefinitions()` method:

```php
PayoutDatastore::class => PayoutDatastoreImpl::class,
PayoutDatastoreHandler::class => PayoutDatabaseHandler::class,
```

Both use the `HasClassDefinitions` interface.

### How it works

The `database-datastore` recipe does not define its own `files` or `registrations`. Instead, it defines a `recipes` array that references the five child recipes and maps variables from the parent into each child. The child recipes execute sequentially, and each one creates its files and performs its registrations independently. See [Recipe Stacking](#recipe-stacking) for how this mechanism works.

---

## Recipe stacking

Recipe stacking lets a recipe reference other recipes instead of (or in addition to) defining its own files. A parent recipe declares a `recipes` array, and each entry names a child recipe and maps variables from the parent scope into the child's variable scope.

### How it works

When the scaffolder encounters a `recipes` array in a recipe spec, it processes each child entry in order:

1. The child recipe is loaded by name (the same lookup used for `--from`).
2. The parent's variables are merged with any overrides specified in the child entry's `vars` object.
3. The `rootNamespace` variable is automatically computed from the project's PSR-4 config, giving child recipes the base namespace they need to construct FQCNs for cross-references.
4. The child recipe runs through the full pipeline: preflight validation, file generation, and registration.
5. Execution continues with the next child.

This means composite recipes get all the same behavior as running each child recipe individually. Preflight validation, duplicate detection, and auto-registration all apply normally.

### The rootNamespace variable

When recipes are stacked, child recipes often need to reference classes created by sibling recipes. For example, the `database-handler` recipe needs the FQCN of the model, model adapter, and table classes. The `rootNamespace` variable is auto-computed from the project's PSR-4 autoload configuration in `composer.json` and made available to all child recipes. A project with `"App\\": "lib/"` in its PSR-4 config would get `rootNamespace` set to `App`.

### Example

The `database-datastore` composite recipe looks roughly like this:

```json
{
  "name": "database-datastore",
  "description": "Creates a full database-backed datastore with model, adapter, table, and handlers",
  "vars": {
    "name": { "type": "string", "description": "Feature name in PascalCase" },
    "tableName": { "type": "string", "description": "Database table name" },
    "initializer": { "type": "string", "description": "FQCN of the initializer" }
  },
  "recipes": [
    { "recipe": "model", "vars": {} },
    { "recipe": "model-adapter", "vars": { "model": "{{rootNamespace}}\\Models\\{{name}}" } },
    { "recipe": "table", "vars": {} },
    { "recipe": "datastore", "vars": {} },
    {
      "recipe": "database-handler",
      "vars": {
        "handlerInterface": "{{rootNamespace}}\\Datastores\\{{name}}\\{{name}}DatastoreHandler",
        "model": "{{rootNamespace}}\\Models\\{{name}}",
        "modelAdapter": "{{rootNamespace}}\\Adapters\\{{name}}Adapter",
        "table": "{{rootNamespace}}\\Tables\\{{name}}Table"
      }
    }
  ]
}
```

Running `phpnomad make --from=database-datastore '{"name":"Payout","tableName":"payouts","initializer":"App\\AppInit"}'` executes all five child recipes in sequence, producing seven files and two registrations from a single command.

---

## Auto-registration behavior

All recipes that include a `registrations` section in their JSON spec trigger auto-registration in the target initializer file. The scaffolder uses [nikic/php-parser](https://github.com/nikic/PHP-Parser) to modify the AST, so the rest of your file (formatting, comments, whitespace) is preserved.

Three scenarios determine what happens when a registration is performed.

### Method already exists

If the initializer already has the target method (e.g. `getListeners()`), the scaffolder finds the `return` statement, locates the array literal, and appends the new entry. For `list` type registrations, it appends a new `ClassName::class` item. For `map` type registrations, it appends a new `KeyClass::class => ValueClass::class` entry.

If the event key already exists in a map registration (e.g. you already have a listener for `UserCreated`), the mutator wraps both values into a nested array rather than creating a duplicate key.

### Method does not exist

If the initializer does not have the target method at all, the scaffolder creates the entire method with the correct return array, adds the corresponding `Has*` interface (e.g. `HasListeners`, `HasCommands`, `HasControllers`) to the class's `implements` list, and inserts the `use` statement at the top of the file.

This means a freshly created initializer with no event handling can receive a listener registration, and the scaffolder will add everything it needs in one pass.

### Duplicate detection

Before appending any entry, the scaffolder checks whether the exact same registration already exists in the return array. For list registrations, it compares `::class` constant fetches by FQCN. For map registrations, it compares both the key and value FQCNs, including values nested inside arrays.

If a duplicate is found, the scaffolder reports "Already registered" and skips the modification. No file is written.

### Complex return statements

If the return statement is not a simple array literal (for example, it uses `array_merge()` or a conditional), the scaffolder cannot safely modify it. In this case, it prints an error with a manual instruction showing exactly what entry to add by hand.

---

## What's next

- **[Scaffolder Introduction](introduction)** covers the full architecture and engine components.
- **[Recipe Spec](recipe-spec)** documents the JSON schema for writing your own custom recipes.
- **[Commands](../commands/introduction)** covers the `make` command and the other CLI commands.
