---
id: mutator-interface-mutation-adapter
slug: docs/packages/mutator/interfaces/mutation-adapter
title: MutationAdapter Interface
doc_type: reference
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: The MutationAdapter interface provides bidirectional conversion between raw data and Mutator instances.
llm_summary: >
  The MutationAdapter interface separates data marshaling from transformation logic. It defines two
  methods: convertFromSource(...$args) creates a Mutator from input data, and convertToResult(Mutator)
  extracts the output from a mutated Mutator. This enables clean separation of concerns where adapters
  handle I/O format conversion and mutators contain pure transformation logic. Used with the
  CanMutateFromAdapter trait for automatic workflow handling.
questions_answered:
  - What is MutationAdapter?
  - How do I convert data to and from Mutators?
  - Why separate conversion from transformation?
  - How does MutationAdapter work with CanMutateFromAdapter?
audience:
  - developers
  - backend engineers
tags:
  - interface
  - mutator
  - adapter
llm_tags:
  - mutation-adapter
  - data-conversion
  - adapter-pattern
keywords:
  - MutationAdapter interface
  - data conversion
  - adapter pattern
related:
  - ../introduction
  - mutator
see_also:
  - ../traits/can-mutate-from-adapter
  - mutator-handler
noindex: false
---

# MutationAdapter Interface

The `MutationAdapter` interface provides **bidirectional conversion** between raw data and `Mutator` instances. It separates data marshaling from transformation logic.

## Interface definition

```php
namespace PHPNomad\Mutator\Interfaces;

interface MutationAdapter
{
    public function convertFromSource(...$args): Mutator;
    public function convertToResult(Mutator $mutator);
}
```

## Methods

### `convertFromSource(...$args): Mutator`

Creates a Mutator instance from input data.

**Parameters:**
- `...$args` — Variadic arguments representing the input data

**Returns:** `Mutator` — A mutator instance ready to transform

### `convertToResult(Mutator $mutator): mixed`

Extracts the result from a mutated Mutator.

**Parameters:**
- `$mutator` — The mutator after `mutate()` has been called

**Returns:** `mixed` — The transformation result in the desired format

---

## The adapter workflow

```
Input data
    │
    ▼
┌───────────────────────────────┐
│  convertFromSource($args)     │
│  → creates Mutator instance   │
└───────────────────────────────┘
    │
    ▼
┌───────────────────────────────┐
│  Mutator::mutate()            │
│  → transforms internal state  │
└───────────────────────────────┘
    │
    ▼
┌───────────────────────────────┐
│  convertToResult($mutator)    │
│  → extracts output data       │
└───────────────────────────────┘
    │
    ▼
Output data
```

---

## Why use adapters?

Adapters provide separation of concerns:

| Component | Responsibility |
|-----------|----------------|
| **Adapter** | Data format conversion (JSON, arrays, objects) |
| **Mutator** | Pure transformation logic |

This separation enables:
- **Reusable mutators** — Same mutator with different input/output formats
- **Testable components** — Test conversion and logic independently
- **Flexible I/O** — Easily swap input/output formats

---

## Basic implementation

```php
use PHPNomad\Mutator\Interfaces\Mutator;
use PHPNomad\Mutator\Interfaces\MutationAdapter;

// The mutator contains transformation logic
class SlugifyMutator implements Mutator
{
    private string $input;
    private string $result = '';

    public function __construct(string $input)
    {
        $this->input = $input;
    }

    public function mutate(): void
    {
        $this->result = strtolower(
            preg_replace('/[^a-zA-Z0-9]+/', '-', $this->input)
        );
    }

    public function getResult(): string
    {
        return $this->result;
    }
}

// The adapter handles data conversion
class SlugAdapter implements MutationAdapter
{
    public function convertFromSource(...$args): Mutator
    {
        return new SlugifyMutator($args[0]);
    }

    public function convertToResult(Mutator $mutator)
    {
        /** @var SlugifyMutator $mutator */
        return $mutator->getResult();
    }
}
```

---

## With validation results

Adapters can shape the output format:

```php
class ContactFormAdapter implements MutationAdapter
{
    public function convertFromSource(...$args): Mutator
    {
        // Expects array input
        return new ContactFormMutator($args[0]);
    }

    public function convertToResult(Mutator $mutator)
    {
        /** @var ContactFormMutator $mutator */
        if ($mutator->isValid()) {
            return [
                'success' => true,
                'data' => $mutator->getData(),
            ];
        }

        return [
            'success' => false,
            'errors' => $mutator->getErrors(),
        ];
    }
}
```

---

## Different output formats

The same mutator can have multiple adapters for different output needs:

```php
// JSON API response adapter
class JsonSlugAdapter implements MutationAdapter
{
    public function convertFromSource(...$args): Mutator
    {
        $data = json_decode($args[0], true);
        return new SlugifyMutator($data['text']);
    }

    public function convertToResult(Mutator $mutator)
    {
        return json_encode(['slug' => $mutator->getResult()]);
    }
}

// Simple string adapter
class StringSlugAdapter implements MutationAdapter
{
    public function convertFromSource(...$args): Mutator
    {
        return new SlugifyMutator($args[0]);
    }

    public function convertToResult(Mutator $mutator)
    {
        return $mutator->getResult();
    }
}
```

---

## Using with CanMutateFromAdapter

The [CanMutateFromAdapter](../traits/can-mutate-from-adapter) trait automates the workflow:

```php
use PHPNomad\Mutator\Traits\CanMutateFromAdapter;

class SlugService
{
    use CanMutateFromAdapter;

    protected MutationAdapter $mutationAdapter;

    public function __construct()
    {
        $this->mutationAdapter = new SlugAdapter();
    }
}

// The trait provides mutate() that handles the full workflow
$service = new SlugService();
echo $service->mutate('Hello World!'); // "hello-world-"
```

---

## Best practices

### Keep adapters focused on conversion

Adapters should only convert data, not contain business logic:

```php
// Good: adapter just converts
class UserAdapter implements MutationAdapter
{
    public function convertFromSource(...$args): Mutator
    {
        return new ValidateUserMutator($args[0]);
    }

    public function convertToResult(Mutator $mutator)
    {
        return $mutator->getValidatedData();
    }
}

// Bad: adapter contains validation logic
class UserAdapter implements MutationAdapter
{
    public function convertFromSource(...$args): Mutator
    {
        // Don't validate here!
        if (empty($args[0]['email'])) {
            throw new Exception('Email required');
        }
        return new ValidateUserMutator($args[0]);
    }
}
```

### Type-hint the mutator in convertToResult

```php
public function convertToResult(Mutator $mutator)
{
    /** @var ContactFormMutator $mutator */
    return $mutator->getData();
}
```

### Handle conversion errors gracefully

```php
public function convertFromSource(...$args): Mutator
{
    if (!isset($args[0]) || !is_array($args[0])) {
        return new ContactFormMutator([]);  // Empty input, mutator handles validation
    }
    return new ContactFormMutator($args[0]);
}
```

---

## Testing

Test adapters and mutators independently:

```php
class SlugAdapterTest extends TestCase
{
    public function test_converts_from_source(): void
    {
        $adapter = new SlugAdapter();

        $mutator = $adapter->convertFromSource('Test Input');

        $this->assertInstanceOf(SlugifyMutator::class, $mutator);
    }

    public function test_converts_to_result(): void
    {
        $adapter = new SlugAdapter();
        $mutator = new SlugifyMutator('Test Input');
        $mutator->mutate();

        $result = $adapter->convertToResult($mutator);

        $this->assertEquals('test-input', $result);
    }
}
```

---

## See also

- [Mutator](mutator) — The transformation interface adapters work with
- [CanMutateFromAdapter](../traits/can-mutate-from-adapter) — Trait that automates the adapter workflow
- [MutatorHandler](mutator-handler) — Simpler alternative when adapters aren't needed
