---
id: mutator-interface-mutator
slug: docs/packages/mutator/interfaces/mutator
title: Mutator Interface
doc_type: reference
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: The Mutator interface defines stateful transformation objects that modify their internal state when mutate() is called.
llm_summary: >
  The Mutator interface is the core transformation contract in phpnomad/mutator. It defines a single
  mutate(): void method for stateful transformations where the object holds input data, transforms it
  internally, and provides results via additional getter methods. Used with MutationAdapter for the
  convert-mutate-convert pattern. Ideal for complex, multi-step transformations that need state tracking.
questions_answered:
  - What is the Mutator interface?
  - How do I implement Mutator?
  - When should I use Mutator vs MutatorHandler?
  - How does Mutator work with MutationAdapter?
audience:
  - developers
  - backend engineers
tags:
  - interface
  - mutator
  - transformation
llm_tags:
  - mutator-interface
  - stateful-transformation
keywords:
  - Mutator interface
  - mutate method
  - stateful transformation
related:
  - ../introduction
  - mutation-adapter
see_also:
  - mutator-handler
  - ../traits/can-mutate-from-adapter
noindex: false
---

# Mutator Interface

The `Mutator` interface defines **stateful transformation objects**. When you call `mutate()`, the object transforms its internal state. You then retrieve results via additional methods on the implementing class.

## Interface definition

```php
namespace PHPNomad\Mutator\Interfaces;

interface Mutator
{
    public function mutate(): void;
}
```

## Methods

### `mutate(): void`

Performs the transformation on the object's internal state.

**Parameters:** None

**Returns:** `void` — Results are accessed via additional methods on the implementing class

**Behavior:**
- Should be idempotent when called multiple times (same result each time)
- May set internal error state rather than throwing exceptions
- Should not return a value; use getter methods for results

---

## When to use Mutator

Use `Mutator` when:

| Scenario | Why Mutator fits |
|----------|------------------|
| Multi-step transformations | Internal state tracks progress through steps |
| Validation + transformation | Can accumulate errors and valid data separately |
| Complex input processing | Constructor receives input; mutate() processes it |
| Paired with MutationAdapter | Clean separation of conversion and logic |

---

## Basic implementation

```php
use PHPNomad\Mutator\Interfaces\Mutator;

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

// Usage
$mutator = new SlugifyMutator('Hello World!');
$mutator->mutate();
echo $mutator->getResult(); // "hello-world-"
```

---

## With validation and errors

```php
class ContactFormMutator implements Mutator
{
    private array $input;
    private array $data = [];
    private array $errors = [];

    public function __construct(array $input)
    {
        $this->input = $input;
    }

    public function mutate(): void
    {
        // Validate email
        $email = trim($this->input['email'] ?? '');
        if (empty($email)) {
            $this->errors['email'] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->errors['email'] = 'Invalid email format';
        } else {
            $this->data['email'] = strtolower($email);
        }

        // Validate name
        $name = trim($this->input['name'] ?? '');
        if (empty($name)) {
            $this->errors['name'] = 'Name is required';
        } else {
            $this->data['name'] = ucwords(strtolower($name));
        }
    }

    public function isValid(): bool
    {
        return empty($this->errors);
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
```

---

## Using with MutationAdapter

The `Mutator` interface is designed to work with [MutationAdapter](mutation-adapter) for clean separation:

```php
class ContactFormAdapter implements MutationAdapter
{
    public function convertFromSource(...$args): Mutator
    {
        return new ContactFormMutator($args[0]);
    }

    public function convertToResult(Mutator $mutator)
    {
        /** @var ContactFormMutator $mutator */
        return $mutator->isValid()
            ? ['success' => true, 'data' => $mutator->getData()]
            : ['success' => false, 'errors' => $mutator->getErrors()];
    }
}
```

---

## Best practices

### Keep mutators focused

Each mutator should do one transformation well:

```php
// Good: focused mutators
class TrimMutator implements Mutator { ... }
class ValidateEmailMutator implements Mutator { ... }
class SanitizeHtmlMutator implements Mutator { ... }

// Bad: too many responsibilities
class StringMutator implements Mutator {
    public function trim() { ... }
    public function validateEmail() { ... }
    public function sanitizeHtml() { ... }
}
```

### Make mutate() idempotent

Calling `mutate()` multiple times should produce the same result:

```php
$mutator = new SlugifyMutator('Hello');
$mutator->mutate();
$mutator->mutate(); // Same result
```

### Provide clear getter methods

Name getters to indicate what they return:

```php
// Good: clear names
public function getResult(): string { ... }
public function getErrors(): array { ... }
public function isValid(): bool { ... }

// Bad: ambiguous
public function get() { ... }
public function status() { ... }
```

---

## Testing

```php
class SlugifyMutatorTest extends TestCase
{
    public function test_mutates_string_to_slug(): void
    {
        $mutator = new SlugifyMutator('Hello World!');
        $mutator->mutate();

        $this->assertEquals('hello-world-', $mutator->getResult());
    }

    public function test_handles_special_characters(): void
    {
        $mutator = new SlugifyMutator('Café & Résumé');
        $mutator->mutate();

        $this->assertStringContainsString('-', $mutator->getResult());
    }

    public function test_is_idempotent(): void
    {
        $mutator = new SlugifyMutator('Test');
        $mutator->mutate();
        $first = $mutator->getResult();

        $mutator->mutate();
        $second = $mutator->getResult();

        $this->assertEquals($first, $second);
    }
}
```

---

## See also

- [MutatorHandler](mutator-handler) — Simpler functional alternative
- [MutationAdapter](mutation-adapter) — Pairs with Mutator for data conversion
- [CanMutateFromAdapter](../traits/can-mutate-from-adapter) — Trait that automates the adapter workflow
