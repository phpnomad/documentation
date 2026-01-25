---
id: mutator-interface-mutation-strategy
slug: docs/packages/mutator/interfaces/mutation-strategy
title: MutationStrategy Interface
doc_type: reference
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: The MutationStrategy interface registers mutator handlers to named actions for dynamic dispatch.
llm_summary: >
  The MutationStrategy interface enables dynamic transformation pipelines by registering MutatorHandler
  instances (via callable factories) to named action strings. The attach(callable, string) method
  registers a handler getter to an action name. Implementations can then invoke handlers by action name.
  Used for building plugin systems, hook-based transformations, and composable pipelines.
questions_answered:
  - What is MutationStrategy?
  - How do I register handlers to named actions?
  - How do I build transformation pipelines?
  - Why use callable factories instead of handlers directly?
audience:
  - developers
  - backend engineers
tags:
  - interface
  - mutator
  - strategy
  - pipeline
llm_tags:
  - mutation-strategy
  - named-actions
  - pipeline-pattern
keywords:
  - MutationStrategy interface
  - named actions
  - transformation pipeline
related:
  - ../introduction
  - mutator-handler
see_also:
  - has-mutations
  - mutator
noindex: false
---

# MutationStrategy Interface

The `MutationStrategy` interface enables **dynamic transformation pipelines** by registering handlers to named actions. This supports plugin-style architectures where transformations are composed at runtime.

## Interface definition

```php
namespace PHPNomad\Mutator\Interfaces;

interface MutationStrategy
{
    public function attach(callable $mutatorGetter, string $action): void;
}
```

## Methods

### `attach(callable $mutatorGetter, string $action): void`

Registers a handler factory to a named action.

**Parameters:**
- `$mutatorGetter` — A callable that returns a `MutatorHandler` instance
- `$action` — The action name to attach the handler to

**Returns:** `void`

**Note:** The first parameter is a **callable factory**, not the handler itself. This enables lazy instantiation—handlers are only created when the action is invoked.

---

## Why use MutationStrategy?

| Scenario | How MutationStrategy helps |
|----------|---------------------------|
| Plugin systems | Plugins register handlers without knowing each other |
| Hook-based transformations | Named hooks trigger registered transformations |
| Composable pipelines | Chain multiple handlers on the same action |
| Runtime configuration | Register handlers based on configuration |

---

## Basic implementation

```php
use PHPNomad\Mutator\Interfaces\MutationStrategy;
use PHPNomad\Mutator\Interfaces\MutatorHandler;

class SimpleMutationStrategy implements MutationStrategy
{
    private array $handlers = [];

    public function attach(callable $mutatorGetter, string $action): void
    {
        $this->handlers[$action][] = $mutatorGetter;
    }

    public function apply(string $action, ...$args)
    {
        $value = $args[0] ?? null;

        foreach ($this->handlers[$action] ?? [] as $getter) {
            /** @var MutatorHandler $handler */
            $handler = $getter();
            $value = $handler->mutate($value);
        }

        return $value;
    }
}
```

---

## Registering handlers

```php
$strategy = new SimpleMutationStrategy();

// Register handlers using callable factories
$strategy->attach(
    fn() => new TrimHandler(),
    'sanitize.string'
);

$strategy->attach(
    fn() => new LowercaseHandler(),
    'sanitize.string'
);

$strategy->attach(
    fn() => new HtmlEscapeHandler(),
    'sanitize.string'
);

// Apply the pipeline
$result = $strategy->apply('sanitize.string', '  <script>HELLO</script>  ');
// Result: "&lt;script&gt;hello&lt;/script&gt;"
```

---

## Why callable factories?

The interface takes a callable that **returns** a handler rather than the handler itself:

```php
// This is what attach() expects
$strategy->attach(fn() => new ExpensiveHandler(), 'action');

// NOT this
$strategy->attach(new ExpensiveHandler(), 'action');  // Wrong!
```

Benefits of callable factories:

| Benefit | Explanation |
|---------|-------------|
| **Lazy instantiation** | Handlers created only when action is invoked |
| **Fresh instances** | Each invocation can get a new handler instance |
| **Dependency injection** | Factory can pull from DI container |
| **Conditional creation** | Factory can include logic for which handler to create |

---

## With dependency injection

```php
class AppMutationStrategy implements MutationStrategy
{
    private Container $container;
    private array $handlers = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function attach(callable $mutatorGetter, string $action): void
    {
        $this->handlers[$action][] = $mutatorGetter;
    }

    public function apply(string $action, ...$args)
    {
        $value = $args[0] ?? null;

        foreach ($this->handlers[$action] ?? [] as $getter) {
            $handler = $getter();
            $value = $handler->mutate($value);
        }

        return $value;
    }
}

// Registration with DI
$strategy->attach(
    fn() => $container->get(ValidateEmailHandler::class),
    'user.validate'
);
```

---

## Multiple actions

Register handlers to different actions for organized pipelines:

```php
// Validation pipeline
$strategy->attach(fn() => new RequiredFieldHandler(), 'user.validate');
$strategy->attach(fn() => new EmailFormatHandler(), 'user.validate');

// Sanitization pipeline
$strategy->attach(fn() => new TrimHandler(), 'user.sanitize');
$strategy->attach(fn() => new LowercaseEmailHandler(), 'user.sanitize');

// Transformation pipeline
$strategy->attach(fn() => new HashPasswordHandler(), 'user.transform');

// Apply in order
$data = $strategy->apply('user.sanitize', $input);
$data = $strategy->apply('user.validate', $data);
$data = $strategy->apply('user.transform', $data);
```

---

## Named action conventions

Use namespaced action names for organization:

```php
// Good: namespaced actions
'user.validate'
'user.sanitize'
'post.render.content'
'post.render.excerpt'

// Bad: flat names that may collide
'validate'
'sanitize'
'render'
```

---

## Best practices

### Use lazy factories

```php
// Good: handler created only when needed
$strategy->attach(fn() => new ExpensiveHandler(), 'action');

// Bad: handler created immediately (if storing reference)
$handler = new ExpensiveHandler();
$strategy->attach(fn() => $handler, 'action');
```

### Order matters for pipelines

Handlers are typically invoked in registration order:

```php
// This order matters!
$strategy->attach(fn() => new TrimHandler(), 'sanitize');      // First
$strategy->attach(fn() => new LowercaseHandler(), 'sanitize'); // Second
$strategy->attach(fn() => new SlugifyHandler(), 'sanitize');   // Third
```

### Return values flow through the pipeline

Each handler receives the previous handler's output:

```php
// Input: "  HELLO WORLD  "
// After TrimHandler: "HELLO WORLD"
// After LowercaseHandler: "hello world"
// After SlugifyHandler: "hello-world"
```

---

## Testing

```php
class MutationStrategyTest extends TestCase
{
    public function test_attaches_handler_to_action(): void
    {
        $strategy = new SimpleMutationStrategy();
        $called = false;

        $strategy->attach(function() use (&$called) {
            $called = true;
            return new class implements MutatorHandler {
                public function mutate(...$args) { return $args[0]; }
            };
        }, 'test.action');

        $strategy->apply('test.action', 'value');

        $this->assertTrue($called);
    }

    public function test_applies_multiple_handlers_in_order(): void
    {
        $strategy = new SimpleMutationStrategy();

        $strategy->attach(
            fn() => new class implements MutatorHandler {
                public function mutate(...$args) { return $args[0] . 'A'; }
            },
            'test'
        );

        $strategy->attach(
            fn() => new class implements MutatorHandler {
                public function mutate(...$args) { return $args[0] . 'B'; }
            },
            'test'
        );

        $result = $strategy->apply('test', '');

        $this->assertEquals('AB', $result);
    }
}
```

---

## See also

- [MutatorHandler](mutator-handler) — The handlers attached to actions
- [HasMutations](has-mutations) — Objects that advertise their mutations
- [Loader Package](/packages/loader/introduction) — Uses MutationStrategy for module initialization
