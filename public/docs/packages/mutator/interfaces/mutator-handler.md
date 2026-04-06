---
id: mutator-interface-mutator-handler
slug: docs/packages/mutator/interfaces/mutator-handler
title: MutatorHandler Interface
doc_type: reference
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: The MutatorHandler interface defines functional-style transformations that take arguments and return results directly.
llm_summary: >
  The MutatorHandler interface provides a functional alternative to Mutator. Instead of stateful
  transformation, it takes variadic arguments and returns the result directly via mutate(...$args).
  Simpler than Mutator when you don't need state management. Used with MutationStrategy for
  building transformation pipelines with named actions.
questions_answered:
  - What is MutatorHandler?
  - How does MutatorHandler differ from Mutator?
  - When should I use MutatorHandler?
  - How do I build pipelines with MutatorHandler?
audience:
  - developers
  - backend engineers
tags:
  - interface
  - mutator
  - transformation
llm_tags:
  - mutator-handler
  - functional-transformation
keywords:
  - MutatorHandler interface
  - functional transformation
  - variadic arguments
related:
  - ../introduction
  - mutation-strategy
see_also:
  - mutator
  - mutation-adapter
noindex: false
---

# MutatorHandler Interface

The `MutatorHandler` interface defines **functional-style transformations**. Unlike `Mutator`, it takes arguments directly and returns the result—no state management required.

## Interface definition

```php
namespace PHPNomad\Mutator\Interfaces;

interface MutatorHandler
{
    public function mutate(...$args);
}
```

## Methods

### `mutate(...$args): mixed`

Performs a transformation on the provided arguments.

**Parameters:**
- `...$args` — Variadic arguments passed to the transformation

**Returns:** `mixed` — The transformed result

**Behavior:**
- Should be stateless (same input always produces same output)
- Returns the result directly
- Can accept any number of arguments

---

## When to use MutatorHandler

Use `MutatorHandler` when:

| Scenario | Why MutatorHandler fits |
|----------|-------------------------|
| Simple transformations | No state management overhead |
| Pure functions | Input → output with no side effects |
| Pipeline building | Easy to chain with MutationStrategy |
| Closures as handlers | Anonymous implementations work well |

---

## Basic implementation

```php
use PHPNomad\Mutator\Interfaces\MutatorHandler;

class DiscountHandler implements MutatorHandler
{
    public function mutate(...$args)
    {
        [$price, $percentage] = $args;
        return $price - ($price * $percentage / 100);
    }
}

// Usage
$handler = new DiscountHandler();
echo $handler->mutate(100, 20); // 80
echo $handler->mutate(50, 10);  // 45
```

---

## Multiple arguments

```php
class FormatCurrencyHandler implements MutatorHandler
{
    public function mutate(...$args)
    {
        [$amount, $currency, $locale] = $args + [null, 'USD', 'en_US'];

        return number_format($amount, 2) . ' ' . $currency;
    }
}

$handler = new FormatCurrencyHandler();
echo $handler->mutate(1234.5);           // "1,234.50 USD"
echo $handler->mutate(1234.5, 'EUR');    // "1,234.50 EUR"
```

---

## Using with MutationStrategy

`MutatorHandler` is designed to work with [MutationStrategy](mutation-strategy) for building pipelines:

```php
class PipelineStrategy implements MutationStrategy
{
    private array $handlers = [];

    public function attach(callable $mutatorGetter, string $action): void
    {
        $this->handlers[$action][] = $mutatorGetter;
    }

    public function apply(string $action, $value)
    {
        foreach ($this->handlers[$action] ?? [] as $getter) {
            /** @var MutatorHandler $handler */
            $handler = $getter();
            $value = $handler->mutate($value);
        }
        return $value;
    }
}

// Register handlers
$pipeline = new PipelineStrategy();

$pipeline->attach(
    fn() => new class implements MutatorHandler {
        public function mutate(...$args) { return trim($args[0]); }
    },
    'sanitize.string'
);

$pipeline->attach(
    fn() => new class implements MutatorHandler {
        public function mutate(...$args) { return strtolower($args[0]); }
    },
    'sanitize.string'
);

// Apply pipeline
echo $pipeline->apply('sanitize.string', '  HELLO  '); // "hello"
```

---

## Anonymous implementations

For simple handlers, anonymous classes work well:

```php
$trimHandler = new class implements MutatorHandler {
    public function mutate(...$args) {
        return trim($args[0]);
    }
};

$upperHandler = new class implements MutatorHandler {
    public function mutate(...$args) {
        return strtoupper($args[0]);
    }
};

echo $upperHandler->mutate($trimHandler->mutate('  hello  ')); // "HELLO"
```

---

## Mutator vs MutatorHandler

| Aspect | Mutator | MutatorHandler |
|--------|---------|----------------|
| State | Stateful (holds input/output) | Stateless |
| Method signature | `mutate(): void` | `mutate(...$args): mixed` |
| Results | Via getter methods | Direct return value |
| Complexity | More setup, more control | Simpler, less control |
| Testing | Test state after mutate() | Test return value directly |
| Use case | Validation, multi-step | Pure transformations |

**Choose MutatorHandler** for simple, pure transformations.
**Choose Mutator** when you need state tracking or validation.

---

## Best practices

### Keep handlers pure

Handlers should have no side effects:

```php
// Good: pure function
class DoubleHandler implements MutatorHandler
{
    public function mutate(...$args)
    {
        return $args[0] * 2;
    }
}

// Bad: side effects
class DoubleHandler implements MutatorHandler
{
    public function mutate(...$args)
    {
        file_put_contents('log.txt', $args[0]); // Side effect!
        return $args[0] * 2;
    }
}
```

### Document expected arguments

```php
/**
 * Applies a percentage discount to a price.
 *
 * @param float $price The original price
 * @param float $percentage The discount percentage (0-100)
 * @return float The discounted price
 */
class DiscountHandler implements MutatorHandler
{
    public function mutate(...$args)
    {
        [$price, $percentage] = $args;
        return $price - ($price * $percentage / 100);
    }
}
```

### Handle missing arguments gracefully

```php
class SafeDiscountHandler implements MutatorHandler
{
    public function mutate(...$args)
    {
        $price = $args[0] ?? 0;
        $percentage = $args[1] ?? 0;

        return $price - ($price * $percentage / 100);
    }
}
```

---

## Testing

```php
class DiscountHandlerTest extends TestCase
{
    public function test_applies_discount(): void
    {
        $handler = new DiscountHandler();

        $result = $handler->mutate(100, 20);

        $this->assertEquals(80, $result);
    }

    public function test_handles_zero_discount(): void
    {
        $handler = new DiscountHandler();

        $result = $handler->mutate(100, 0);

        $this->assertEquals(100, $result);
    }

    public function test_is_stateless(): void
    {
        $handler = new DiscountHandler();

        $first = $handler->mutate(100, 10);
        $second = $handler->mutate(100, 10);

        $this->assertEquals($first, $second);
    }
}
```

---

## See also

- [Mutator](mutator) — Stateful alternative for complex transformations
- [MutationStrategy](mutation-strategy) — Register handlers to named actions
- [HasMutations](has-mutations) — Advertise available handlers
