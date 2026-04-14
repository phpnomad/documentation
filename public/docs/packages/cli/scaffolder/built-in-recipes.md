---
id: cli-scaffolder-built-in-recipes
slug: docs/packages/cli/scaffolder/built-in-recipes
title: Built-in Recipes
doc_type: reference
status: active
language: en
owner: docs-team
last_reviewed: 2026-04-13
summary: Reference for the four built-in scaffolder recipes, including generated output, required variables, and auto-registration behavior.
llm_summary: >
  Documents the four built-in recipes bundled with the PHPNomad CLI scaffolder: listener, event,
  command, and controller. Each recipe section covers what the recipe creates, what variables it
  requires, the full generated file output from its .php.tpl template, what auto-registration it
  performs in initializer files (including the Has* interface, method name, and registration type),
  and a complete command example. Also covers the three auto-registration scenarios: appending to
  an existing method, creating the method from scratch (with interface and use statement), and
  duplicate detection that prevents double-registration.
questions_answered:
  - What built-in recipes does the PHPNomad scaffolder include?
  - How do I scaffold a listener with the PHPNomad CLI?
  - How do I scaffold an event with the PHPNomad CLI?
  - How do I scaffold a CLI command with the PHPNomad CLI?
  - How do I scaffold a REST controller with the PHPNomad CLI?
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
llm_tags:
  - scaffolder-recipes
  - listener-recipe
  - event-recipe
  - command-recipe
  - controller-recipe
  - auto-registration
keywords:
  - phpnomad make
  - built-in recipes
  - listener scaffolding
  - event scaffolding
  - command scaffolding
  - controller scaffolding
  - auto-registration
  - HasListeners
  - HasCommands
  - HasControllers
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

The PHPNomad CLI ships with four built-in recipes that cover the most common scaffolding tasks: event listeners, events, CLI commands, and REST controllers. Each recipe generates a PHP file from a template and, where applicable, auto-registers the new class in an initializer.

Built-in recipes are referenced by name. When you pass `--from=listener`, the scaffolder looks for `listener.json` in its bundled `Recipes/` directory. You do not need a file path for these.

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
