---
id: cli-scaffolder-recipe-spec
slug: docs/packages/cli/scaffolder/recipe-spec
title: Recipe JSON Specification
doc_type: reference
status: active
language: en
owner: docs-team
last_reviewed: 2026-04-13
summary: Complete reference for the JSON recipe format used by the PHPNomad CLI scaffolder.
llm_summary: >
  Full specification for PHPNomad scaffolder recipe JSON files. Covers all top-level fields (name, description, vars,
  requires, files, registrations), registration types (list vs map), variable resolution order, auto-computed case
  transforms, per-file var overrides, and complete examples including the listener recipe and a multi-file datastore
  feature recipe. Also explains how to create and reference custom recipes.
questions_answered:
  - What is the JSON schema for a scaffolder recipe?
  - What fields does a recipe file support?
  - How do recipe variables work?
  - What are the registration types and how do they differ?
  - How does variable resolution order work?
  - What auto-computed transforms are available for variables?
  - How do per-file var overrides work?
  - How do I create a custom recipe?
  - Where do I put custom recipe JSON files?
  - How do I reference a custom recipe from the CLI?
  - What does a complete recipe look like?
  - How do recipe requirements work?
audience:
  - developers
  - backend engineers
  - framework users
tags:
  - cli
  - scaffolder
  - recipes
  - code-generation
  - reference
llm_tags:
  - scaffolder-recipe-spec
  - json-schema
  - code-generation-config
keywords:
  - phpnomad recipe
  - scaffolder recipe
  - recipe json
  - scaffolder spec
  - recipe format
  - recipe schema
related:
  - introduction
  - built-in-recipes
  - ../commands/introduction
see_also:
  - introduction
  - built-in-recipes
noindex: false
---

# Recipe JSON Specification

A recipe is a JSON file that tells the scaffolder what to generate. It declares variables, files, templates, and initializer registrations in a single portable configuration. This document is the complete reference for that format.

If you are looking for a higher-level overview of the scaffolder, see the [introduction](introduction). For the built-in recipes that ship with the CLI, see [built-in recipes](built-in-recipes).

---

## Full Schema

Here is the complete shape of a recipe JSON file, with every field shown:

```json
{
  "name": "string (required)",
  "description": "string",
  "vars": {
    "varName": {
      "type": "string",
      "description": "what this variable is for"
    }
  },
  "requires": [
    {
      "type": "binding",
      "value": "AbstractClassName"
    }
  ],
  "files": [
    {
      "path": "lib/Dir/{{name}}.php",
      "template": "templateName",
      "vars": {
        "override": "value"
      }
    }
  ],
  "registrations": [
    {
      "initializer": "{{initializer}}",
      "method": "getListeners",
      "interface": "PHPNomad\\Events\\Interfaces\\HasListeners",
      "type": "map",
      "key": "{{event}}",
      "value": "{{namespace}}\\{{name}}"
    }
  ]
}
```

Every field except `name` is optional. A recipe with just `name` and `files` is perfectly valid. The sections below cover each field in detail.

---

## Top-Level Fields

### name (required)

The recipe identifier. This is used in CLI output when the recipe runs and as the lookup key for built-in recipes.

```json
{
  "name": "listener"
}
```

When you run `phpnomad make listener`, the CLI looks for a built-in recipe with `"name": "listener"`. For custom recipes loaded with `--from`, the name is just for display purposes.

### description

A human-readable description shown to the user when the recipe executes. Keep it short and descriptive.

```json
{
  "description": "Creates an event listener and registers it in an initializer"
}
```

When the recipe runs, you will see:

```
Recipe: listener
  Creates an event listener and registers it in an initializer
```

### vars

Declares what variables the recipe needs from the user. Each var has a name (the object key), a `type`, and a `description`.

```json
{
  "vars": {
    "name": {
      "type": "string",
      "description": "Listener class name (e.g. SendWelcomeEmail)"
    },
    "event": {
      "type": "string",
      "description": "FQCN of the event class to listen to"
    }
  }
}
```

The `type` field is currently always `"string"`. It exists for forward compatibility. The `description` is shown to the user if a required variable is missing.

Every variable declared here must be provided via CLI flags when the recipe is run. If any are missing, the preflight validator will report an error before any files are created.

### requires

Pre-flight requirements that must be satisfied before the recipe can run. Each requirement has a `type` and a `value`.

```json
{
  "requires": [
    {
      "type": "binding",
      "value": "HasDatastoreHandler"
    }
  ]
}
```

Currently, the only supported type is `"binding"`, which checks the project index to verify that an abstract class or interface is bound somewhere in an initializer's `getClassDefinitions()`. This is useful for recipes that generate code depending on a particular abstraction being available at runtime.

If the project has not been indexed yet (no `.phpnomad/` directory), requirement checks are silently skipped. An empty array means no requirements.

### files

An array of files to generate. Each entry has three fields:

| Field | Required | Description |
|-------|----------|-------------|
| `path` | yes | Output path relative to the project root, with `{{var}}` placeholders |
| `template` | yes | Name of the `.php.tpl` template file (without the extension) |
| `vars` | no | Per-file variable overrides |

```json
{
  "files": [
    {
      "path": "lib/Listeners/{{name}}.php",
      "template": "listener"
    },
    {
      "path": "lib/Events/{{name}}Event.php",
      "template": "event",
      "vars": {
        "eventId": "{{nameSnake}}.fired"
      }
    }
  ]
}
```

The `path` field supports `{{var}}` placeholders, which are resolved from user-provided variables before the file is written. The scaffolder creates any missing directories automatically.

The `template` field refers to a `.php.tpl` file. Built-in templates live in the CLI's `lib/Scaffolder/Templates/` directory. For custom recipes, the template name still references built-in templates by default.

The `vars` field lets you override or add variables for a specific file. See the [Per-File Var Overrides](#per-file-var-overrides) section for details.

The preflight validator checks that none of the output files already exist before the recipe runs. If a file already exists, the recipe aborts with an error.

### registrations

An array of initializer registrations to perform after files are generated. Each registration modifies an existing initializer class to wire up the new code.

| Field | Required | Description |
|-------|----------|-------------|
| `initializer` | yes | FQCN of the initializer class (supports `{{var}}` placeholders) |
| `method` | yes | Method name to register in (e.g. `getListeners`, `getCommands`) |
| `interface` | yes | FQCN of the interface the initializer should implement |
| `type` | yes | Either `"list"` or `"map"` |
| `key` | no | For `"map"` type only. The array key (supports `{{var}}` placeholders) |
| `value` | no | The value to register (supports `{{var}}` placeholders) |

```json
{
  "registrations": [
    {
      "initializer": "{{initializer}}",
      "method": "getListeners",
      "interface": "PHPNomad\\Events\\Interfaces\\HasListeners",
      "type": "map",
      "key": "{{event}}",
      "value": "{{namespace}}\\{{name}}"
    }
  ]
}
```

The scaffolder does several things with each registration:

1. Locates the initializer file using PSR-4 namespace resolution.
2. If the method already exists, appends the new entry to its return array.
3. If the method does not exist, creates it, adds the interface to the class's `implements` list, and adds the appropriate `use` statement.
4. Checks for duplicates and skips if the entry is already present.

If the method exists but its return statement is not a simple array literal, the scaffolder reports an error and provides the manual entry for you to add yourself.

---

## Registration Types

Registrations come in two flavors: `list` and `map`. The type determines how the entry is added to the method's return array.

### list

A `list` registration appends `Value::class` to a flat return array. This is used for methods like `getControllers`, `getCommands`, and `getFacades`.

```json
{
  "type": "list",
  "value": "{{namespace}}\\{{name}}"
}
```

This produces PHP code like:

```php
public function getCommands(): array
{
    return [
        \App\Commands\DeployCommand::class,
    ];
}
```

The `key` field is not used for list registrations.

### map

A `map` registration appends `Key::class => Value::class` to a keyed return array. This is used for methods like `getListeners`, `getClassDefinitions`, and similar associative mappings.

```json
{
  "type": "map",
  "key": "{{event}}",
  "value": "{{namespace}}\\{{name}}"
}
```

This produces PHP code like:

```php
public function getListeners(): array
{
    return [
        \App\Events\UserCreated::class => \App\Listeners\SendWelcomeEmail::class,
    ];
}
```

If the key already exists in the array, the scaffolder converts the value to an array and appends the new entry. This handles the common case where multiple listeners subscribe to the same event.

---

## Variable Resolution

Variables are resolved in a specific order, with later sources overriding earlier ones. Understanding this order is important when building recipes that reuse templates with different values.

### Resolution Order

1. **Auto-computed vars** (lowest priority). The `namespace` variable is always computed from the file's output path using PSR-4 autoload mappings in `composer.json`.
2. **User-provided vars**. These come from CLI flags when the recipe is run, such as `--name=SendWelcomeEmail`.
3. **Per-file vars** (highest priority). These are defined in the `vars` field of a file entry and override everything else for that specific file.

### Auto-Computed Transforms

For every user-provided variable, the scaffolder automatically generates case-transformed variants. If you provide `name=SendWelcomeEmail`, the following variables become available:

| Variable | Value | Transform |
|----------|-------|-----------|
| `{{name}}` | `SendWelcomeEmail` | As provided |
| `{{nameLower}}` | `sendWelcomeEmail` | `lcfirst` (first character lowercased) |
| `{{nameSnake}}` | `send_welcome_email` | `snake_case` |

These transforms apply to all user-provided variables, not just `name`. If you define a variable called `event` with the value `UserCreated`, you also get `{{eventLower}}` (`userCreated`) and `{{eventSnake}}` (`user_created`).

The `{{namespace}}` variable is always available and is computed per-file based on where the file will be written. For registrations, the namespace is taken from the first file in the recipe.

### Recursive Resolution

Variable values can reference other variables using `{{var}}` syntax. The resolver runs up to 10 passes to handle chains of references. For example:

```json
{
  "vars": {
    "fullClass": "{{namespace}}\\{{name}}"
  }
}
```

After `namespace` and `name` are resolved, `fullClass` will contain the complete fully-qualified class name.

---

## Per-File Var Overrides

The `vars` field on a file entry lets you override or add variables for that specific file. This is especially useful when reusing the same template with different values.

Consider a recipe that generates both a "create" and "update" controller using the same controller template:

```json
{
  "name": "crud-pair",
  "description": "Creates a create and update controller pair",
  "vars": {
    "name": {
      "type": "string",
      "description": "Resource name (e.g. User)"
    },
    "initializer": {
      "type": "string",
      "description": "FQCN of the initializer"
    }
  },
  "files": [
    {
      "path": "lib/Rest/Create{{name}}.php",
      "template": "controller",
      "vars": {
        "name": "Create{{name}}",
        "method": "POST",
        "endpoint": "/{{nameSnake}}"
      }
    },
    {
      "path": "lib/Rest/Update{{name}}.php",
      "template": "controller",
      "vars": {
        "name": "Update{{name}}",
        "method": "PUT",
        "endpoint": "/{{nameSnake}}/{{id}}"
      }
    }
  ],
  "registrations": []
}
```

With `--name=Product`, the first file gets `name=CreateProduct`, `method=POST`, and `endpoint=/product`. The second file gets `name=UpdateProduct`, `method=PUT`, and `endpoint=/product/{{id}}`. The per-file vars override the top-level `name` variable within each file's scope only.

---

## Complete Example: Listener Recipe

This is the `listener` recipe that ships with the CLI. It is a straightforward example that creates one file and performs one registration.

```json
{
  "name": "listener",
  "description": "Creates an event listener and registers it in an initializer",
  "vars": {
    "name": {
      "type": "string",
      "description": "Listener class name (e.g. SendWelcomeEmail)"
    },
    "event": {
      "type": "string",
      "description": "FQCN of the event class to listen to"
    },
    "initializer": {
      "type": "string",
      "description": "FQCN of the initializer to register in"
    }
  },
  "requires": [],
  "files": [
    {
      "path": "lib/Listeners/{{name}}.php",
      "template": "listener"
    }
  ],
  "registrations": [
    {
      "initializer": "{{initializer}}",
      "method": "getListeners",
      "interface": "PHPNomad\\Events\\Interfaces\\HasListeners",
      "type": "map",
      "key": "{{event}}",
      "value": "{{namespace}}\\{{name}}"
    }
  ]
}
```

When you run:

```bash
phpnomad make listener \
  --name=SendWelcomeEmail \
  --event="App\\Events\\UserCreated" \
  --initializer="App\\Initializers\\AppInitializer"
```

The scaffolder:

1. Creates `lib/Listeners/SendWelcomeEmail.php` using the `listener.php.tpl` template.
2. Computes the namespace from the file path (e.g. `App\Listeners`).
3. Opens the `AppInitializer` class, finds or creates `getListeners()`, and adds `UserCreated::class => SendWelcomeEmail::class` to the return array.

---

## Complete Example: Multi-File Datastore Feature

A more advanced recipe can generate an entire feature in one command. Here is what a datastore feature recipe might look like, creating a model, table, handler, decorators, service provider, and initializer registrations.

```json
{
  "name": "datastore-feature",
  "description": "Creates a full datastore feature with model, table, handler, and decorator stack",
  "vars": {
    "name": {
      "type": "string",
      "description": "Feature name in PascalCase (e.g. Payout)"
    },
    "tableName": {
      "type": "string",
      "description": "Database table name (e.g. payouts)"
    },
    "initializer": {
      "type": "string",
      "description": "FQCN of the initializer to register in"
    }
  },
  "requires": [
    {
      "type": "binding",
      "value": "HasDatastoreHandler"
    }
  ],
  "files": [
    {
      "path": "lib/Datastores/{{name}}/Models/{{name}}.php",
      "template": "model"
    },
    {
      "path": "lib/Datastores/{{name}}/{{name}}Table.php",
      "template": "table",
      "vars": {
        "tableName": "{{tableName}}"
      }
    },
    {
      "path": "lib/Datastores/{{name}}/{{name}}Handler.php",
      "template": "handler"
    },
    {
      "path": "lib/Datastores/{{name}}/{{name}}Datastore.php",
      "template": "datastore-interface"
    },
    {
      "path": "lib/Datastores/{{name}}/Decorators/{{name}}DatastoreDecorator.php",
      "template": "datastore-decorator"
    },
    {
      "path": "lib/Datastores/{{name}}/Decorators/{{name}}DatastorePrimaryKeyDecorator.php",
      "template": "primary-key-decorator"
    },
    {
      "path": "lib/Datastores/{{name}}/Decorators/{{name}}DatastoreWhereDecorator.php",
      "template": "where-decorator"
    },
    {
      "path": "lib/Datastores/{{name}}/{{name}}ServiceProvider.php",
      "template": "service-provider"
    }
  ],
  "registrations": [
    {
      "initializer": "{{initializer}}",
      "method": "getClassDefinitions",
      "interface": "PHPNomad\\Di\\Interfaces\\HasClassDefinitions",
      "type": "map",
      "key": "{{namespace}}\\{{name}}Datastore",
      "value": "{{namespace}}\\Decorators\\{{name}}DatastoreDecorator"
    },
    {
      "initializer": "{{initializer}}",
      "method": "getServiceProviders",
      "interface": "PHPNomad\\Di\\Interfaces\\HasServiceProviders",
      "type": "list",
      "value": "{{namespace}}\\{{name}}ServiceProvider"
    }
  ]
}
```

Running this recipe with `--name=Payout --tableName=payouts --initializer="App\\Initializers\\AppInitializer"` generates eight files under `lib/Datastores/Payout/` and registers the datastore binding and service provider in the initializer.

Key things to notice:

- **Multiple files from one recipe.** Each entry in `files` creates one file, and they can live in different subdirectories.
- **Per-file var overrides.** The table file gets `tableName` passed through explicitly.
- **Multiple registrations.** Two separate initializer methods are modified: one for class definitions (map type) and one for service providers (list type).
- **Requirements.** The recipe checks that `HasDatastoreHandler` is bound in the project index before running.
- **Namespace in registrations.** The `{{namespace}}` variable in registrations resolves to the namespace of the first file in the list. This is why the first file's location matters when building registrations.

---

## Custom Recipes

You can create your own recipes for patterns specific to your project or team.

### Creating a Recipe

1. Create a JSON file following the schema documented above.
2. Place it anywhere accessible from your project. Common locations include the project root or a `recipes/` directory.
3. Create any custom `.php.tpl` template files your recipe references. Custom recipes can reference built-in templates by name, or you can write your own templates.

### Running a Custom Recipe

Use the `--from` flag to point at your recipe file:

```bash
phpnomad make --from=./recipes/my-feature.json \
  --name=Widget \
  --initializer="App\\Initializers\\AppInitializer"
```

The `--from` path is relative to your current working directory. You can also use an absolute path.

### Path Resolution Rules

The CLI determines whether a recipe name is a built-in or a file path based on two rules:

- If the name contains a `/` or ends with `.json`, it is treated as a file path.
- Otherwise, it is treated as a built-in recipe name and looked up in the CLI's `lib/Scaffolder/Recipes/` directory.

So `phpnomad make listener` loads the built-in listener recipe, while `phpnomad make --from=./listener.json` loads from the local file.

### Template Resolution

Custom recipes reference templates by name in the `template` field. These names correspond to `.php.tpl` files in the CLI's `lib/Scaffolder/Templates/` directory. When building custom recipes, you can reuse any of the built-in template names: `listener`, `event`, `command`, and `controller`.

If you need a template that does not exist yet, you will need to add it to the templates directory or extend the scaffolder.

---

## Field Reference Summary

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | yes | Recipe identifier, used for display and built-in lookup |
| `description` | string | no | Shown to the user when the recipe runs |
| `vars` | object | no | Variable declarations with type and description |
| `vars.*.type` | string | no | Variable type (currently always `"string"`) |
| `vars.*.description` | string | no | Shown to the user if the variable is missing |
| `requires` | array | no | Pre-flight requirement checks |
| `requires[].type` | string | yes | Requirement type (currently only `"binding"`) |
| `requires[].value` | string | yes | Value to check (e.g. abstract class name) |
| `files` | array | no | Files to generate |
| `files[].path` | string | yes | Output path with `{{var}}` placeholders |
| `files[].template` | string | yes | Template name (without `.php.tpl` extension) |
| `files[].vars` | object | no | Per-file variable overrides |
| `registrations` | array | no | Initializer registrations to perform |
| `registrations[].initializer` | string | yes | FQCN of the initializer class |
| `registrations[].method` | string | yes | Method to register in |
| `registrations[].interface` | string | yes | Interface the initializer should implement |
| `registrations[].type` | string | yes | `"list"` or `"map"` |
| `registrations[].key` | string | no | Array key for map registrations |
| `registrations[].value` | string | no | Value to register |
