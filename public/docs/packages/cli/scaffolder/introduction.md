---
id: cli-scaffolder-introduction
slug: docs/packages/cli/scaffolder/introduction
title: Scaffolder
doc_type: explanation
status: active
language: en
owner: docs-team
last_reviewed: 2026-04-13
summary: The scaffolder generates PHP files from JSON recipe specs and auto-registers them in initializer files via AST mutation.
llm_summary: >
  The PHPNomad CLI scaffolder subsystem generates PHP source files from JSON recipe specifications.
  A recipe declares what variables it needs, what files to generate from .php.tpl templates, what
  prerequisites must be met (validated against the project index), and what registrations to perform
  in existing initializer files. Auto-registration uses nikic/php-parser's format-preserving printer
  to mutate PHP ASTs, appending entries to return arrays, adding use statements, and implementing
  interfaces. The engine supports multi-file recipes, per-file var overrides, auto-computed
  variable transforms (nameLower, nameSnake), and recipe stacking where composite recipes reference
  other recipes to scaffold entire features from a single command. Pre-flight validation checks for
  missing vars, existing output files, missing initializers, and unresolved bindings before any
  files are written.
questions_answered:
  - What is the PHPNomad scaffolder?
  - How does code generation work in PHPNomad?
  - What is a recipe spec?
  - How does auto-registration work?
  - What are the scaffolder engine components?
  - How does the scaffolder modify existing PHP files?
  - Can a single recipe create multiple files?
  - What happens during pre-flight validation?
  - How are variables resolved in templates?
  - What is recipe stacking?
  - How do composite recipes work?
audience:
  - developers
  - backend engineers
  - framework contributors
tags:
  - cli
  - scaffolder
  - code-generation
  - package-overview
llm_tags:
  - scaffolder-engine
  - recipe-spec
  - ast-mutation
  - template-rendering
  - auto-registration
  - recipe-stacking
keywords:
  - phpnomad scaffolder
  - recipe spec
  - code generation
  - auto-registration
  - initializer mutator
  - php-parser
  - template rendering
  - recipe stacking
  - composite recipes
related:
  - ../introduction
  - ../indexer/introduction
  - ../commands/introduction
see_also:
  - recipe-spec
  - built-in-recipes
noindex: false
---

# Scaffolder

The scaffolder is the code generation subsystem of the PHPNomad CLI. It turns a JSON recipe spec into real PHP files, then wires those files into your project's initializers automatically. You describe what you want, the scaffolder builds it, and the result is ready to use without any manual glue code.

Everything flows from a single primitive: the **recipe spec**. A recipe is a JSON file that declares what variables it needs, what files to generate, what prerequisites must be met, and what registrations to perform in existing initializer classes. One recipe can create a single file or scaffold an entire feature.

---

## Key ideas at a glance

- **Recipe specs** are JSON blueprints that describe one or more files to generate, the variables they need, and how to register the results.
- **Template rendering** uses `{{var}}` substitution in `.php.tpl` template files to produce PHP source.
- **Auto-registration** modifies existing PHP files using AST-based mutation to wire new classes into initializers.
- **Pre-flight validation** checks requirements against the project index before any files are generated.
- **Per-file var overrides** let the same template produce different output in multi-file recipes.
- **Auto-computed var transforms** derive `nameLower` and `nameSnake` from user-supplied variables automatically.
- **Recipe stacking** lets composite recipes reference other recipes, scaffolding entire features from a single command.

---

## How it works

When you run `phpnomad make --from=listener`, the scaffolder moves through a pipeline of six engine components. Each stage has a single responsibility, and the pipeline stops early if anything fails.

```
+-------------+     +---------------------+     +-------------------+
| Recipe JSON |---->|    RecipeLoader      |---->| PreflightValidator|
+-------------+     | Parse JSON, build   |     | Check vars, files,|
                     | Recipe model        |     | initializers, deps|
                     +---------------------+     +--------+----------+
                                                          |
                                                          | (pass)
                                                          v
                     +-------------------+     +-------------------+
                     |  NamespaceResolver |<----|    VarResolver    |
                     |  PSR-4 path to    |     | Merge user vars,  |
                     |  namespace lookup  |     | file overrides,   |
                     +-------------------+     | auto-transforms   |
                                               +--------+----------+
                                                         |
                                                         v
                     +---------------------+     +-------------------+
                     | InitializerMutator  |<----|TemplateRenderer   |
                     | AST-based PHP file  |     | {{var}} substitution
                     | mutation for auto-  |     | on .php.tpl files |
                     | registration        |     +-------------------+
                     +----------+----------+
                                |
                                v
                            [ Done ]
```

1. **RecipeLoader** reads the JSON spec and builds a `Recipe` model.
2. **PreflightValidator** checks that all required vars are present, output files do not already exist, initializer classes can be found, and any declared bindings exist in the project index.
3. **NamespaceResolver** derives the PHP namespace for each output file from its path and the project's PSR-4 autoload config in `composer.json`.
4. **VarResolver** merges user-supplied variables, per-file overrides, and auto-computed transforms into the final variable map for each file.
5. **TemplateRenderer** performs `{{var}}` substitution on `.php.tpl` template files and writes the results to disk.
6. **InitializerMutator** modifies existing PHP initializer files to register the newly created classes.

---

## The six engine components

### RecipeLoader

Loads and parses JSON recipe specs. If the `--from` value contains a slash or ends with `.json`, it is treated as a file path. Otherwise, it is resolved as a built-in recipe name (e.g., `listener` maps to `Recipes/listener.json` inside the CLI package).

The loader validates the JSON structure and builds a `Recipe` model containing vars, requirements, file definitions, and registration instructions.

### TemplateRenderer

Performs `{{var}}` substitution on `.php.tpl` template files. Templates are plain PHP files with placeholder tokens. The renderer replaces every `{{varName}}` with the resolved value from the variable map.

A listener template, for example, looks like this:

```php
<?php

namespace {{namespace}};

use PHPNomad\Events\Interfaces\CanHandle;
use PHPNomad\Events\Interfaces\Event;
use {{event}};

class {{name}} implements CanHandle
{
    public function __construct(
        // TODO: Add constructor dependencies
    )
    {
    }

    public function handle(Event $event): void
    {
        // TODO: Implement listener logic
    }
}
```

The `{{namespace}}` token is auto-computed from the output path. The `{{name}}` and `{{event}}` tokens come from user input.

### NamespaceResolver

Derives the PHP namespace for a generated file by matching its output path against the project's PSR-4 autoload mappings in `composer.json`. If a file will be written to `lib/Listeners/SendWelcomeEmail.php` and the PSR-4 config maps `App\\` to `lib/`, the resolved namespace is `App\Listeners`.

The resolver also handles the reverse lookup, converting a fully qualified class name back to a file path. This is how the engine locates initializer files for registration.

### VarResolver

Merges three sources of variables into a single map for each file:

1. **User vars** from the CLI command (e.g., `name`, `event`, `initializer`).
2. **Per-file overrides** declared in the recipe's `files[].vars` object.
3. **Auto-computed transforms** derived from user vars.

For every user variable, the resolver automatically generates two transforms:

| User var | Transform | Example |
|----------|-----------|---------|
| `name` = `SendWelcomeEmail` | `nameLower` | `sendWelcomeEmail` |
| `name` = `SendWelcomeEmail` | `nameSnake` | `send_welcome_email` |

File-level overrides can reference other variables using `{{var}}` syntax. The resolver performs up to 10 passes of recursive reference resolution so that overrides can compose from other values.

### PreflightValidator

Checks four things before any files are generated:

1. **Required vars are present.** Every var declared in the recipe must have a value in the user input.
2. **Output files do not already exist.** The scaffolder refuses to overwrite existing files.
3. **Initializer classes exist.** Every registration target must resolve to an existing PHP file on disk.
4. **Binding requirements are satisfied.** If the recipe declares `requires` entries of type `binding`, the validator checks the project index to confirm those bindings are registered.

The project index is built by the [Indexer](../indexer/introduction) subsystem. If no index exists, binding checks are skipped.

If any check fails, the engine prints the errors and exits without writing anything.

### InitializerMutator

The most complex component. It modifies existing PHP files to register newly scaffolded classes. It uses [nikic/php-parser](https://github.com/nikic/PHP-Parser) to parse the initializer file into an AST, modify it, and print the result using the format-preserving printer.

The mutator handles three scenarios:

**Method exists, simple return array.** The mutator finds the return statement, checks for duplicates, and appends a new entry to the array. For `list` type registrations, it adds a `ClassName::class` item. For `map` type registrations, it adds a `KeyClass::class => ValueClass::class` entry.

**Method does not exist.** The mutator creates the method with the correct return array, adds the corresponding `Has*` interface to the class's `implements` list, and inserts the necessary `use` statement at the top of the file.

**Return statement is too complex.** If the return is not a simple array literal (for example, it merges arrays or calls a function), the mutator cannot safely modify it. It returns a failure result with a manual instruction string so the user knows exactly what to add by hand.

The format-preserving printer is important here. It ensures that the rest of the file, including whitespace, comments, and formatting, remains untouched. Only the specific AST nodes that were modified appear differently in the output.

---

## Multi-file recipes

A single recipe can declare multiple entries in its `files` array. Each file gets its own output path, template, and optional var overrides. Combined with multiple `registrations` entries, a single recipe invocation can scaffold an entire feature.

For example, a hypothetical `crud` recipe could create a datastore interface, a database handler, four REST controllers (create, read, update, delete), an event class, and a listener, then register all of them in the appropriate initializers. The user runs one command and gets a complete, wired-up feature skeleton.

Per-file var overrides make this possible. The `files[].vars` object lets you set different variable values for each file while still reusing the same template. A recipe that generates both a `CreatePostController` and an `UpdatePostController` from the same controller template can override the `name` var per file.

---

## Recipe stacking

Beyond multi-file recipes, the scaffolder supports recipe stacking. A composite recipe does not define its own files and registrations. Instead, it declares a `recipes` array that references other recipes by name. Each child recipe executes in sequence with variables flowing from the parent scope.

This is the mechanism behind the `database-datastore` recipe, which stacks five child recipes (model, model-adapter, table, datastore, database-handler) to produce seven files and two initializer registrations from a single command:

```bash
phpnomad make --from=database-datastore '{"name":"Payout","tableName":"payouts","initializer":"App\\AppInit"}'
```

Recipe stacking keeps individual recipes small and reusable. You can run `phpnomad make --from=model` on its own, or let `database-datastore` invoke it as part of a larger operation. The child recipes do not know or care whether they are running standalone or as part of a stack.

The `rootNamespace` variable is auto-computed from the project's PSR-4 config and injected into child recipes so they can construct FQCNs for classes created by sibling recipes. For the full specification, including how variables are inherited and overridden, see [Recipe Spec](recipe-spec#recipe-stacking).

---

## Usage

The scaffolder is invoked through the `make` command. You specify a recipe name (or path) and pass variables as a JSON object:

```bash
phpnomad make --from=listener '{"name":"SendWelcomeEmail","event":"App\\Events\\UserCreated","initializer":"App\\AppInit"}'
```

This command:

1. Loads the built-in `listener` recipe.
2. Validates that `name`, `event`, and `initializer` are all provided.
3. Checks that `lib/Listeners/SendWelcomeEmail.php` does not already exist.
4. Confirms that the `App\AppInit` initializer file exists on disk.
5. Resolves the namespace for `lib/Listeners/SendWelcomeEmail.php` from the PSR-4 config.
6. Renders the listener template with the resolved variables.
7. Writes `lib/Listeners/SendWelcomeEmail.php`.
8. Opens `App\AppInit`, finds or creates `getListeners()`, and adds the `UserCreated::class => SendWelcomeEmail::class` mapping.

The output looks something like this:

```
Recipe: listener
  Creates an event listener and registers it in an initializer
  Created: lib/Listeners/SendWelcomeEmail.php
  Registered: getListeners() in App\AppInit

Done: 1 file(s) created, 1 registration(s) performed.
```

---

## What's next

- **[Recipe Spec](recipe-spec)** covers the full JSON schema for recipe files, including all fields, types, and validation rules.
- **[Built-in Recipes](built-in-recipes)** documents all 16 bundled recipes, from single-file recipes like listener and facade to composite recipes like database-datastore.
- **[Commands](../commands/introduction)** covers the `make` command and other CLI commands.
- **[Indexer](../indexer/introduction)** explains how the project index is built and how it feeds pre-flight validation.
