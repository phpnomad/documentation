---
id: mutator-introduction
slug: docs/packages/mutator/introduction
title: Mutator Package
doc_type: explanation
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: The mutator package provides interfaces and traits for implementing structured data transformation pipelines in PHP.
llm_summary: >
  phpnomad/mutator provides a set of interfaces and one trait for implementing the mutation (transformation)
  pattern in PHP. The package defines Mutator (stateful transformation), MutatorHandler (functional transformation
  with arguments), MutationAdapter (bidirectional conversion between data and mutators), MutationStrategy
  (attaching mutators to named actions), and HasMutations (exposing available mutations). The CanMutateFromAdapter
  trait simplifies using adapters by handling the convert-mutate-convert workflow. Used by the loader package
  for module initialization and by wordpress-plugin for data transformations. Zero dependencies.
questions_answered:
  - What is the mutator package?
  - How do I transform data using mutators?
  - What is the difference between Mutator and MutatorHandler?
  - How do MutationAdapters work?
  - How do I attach mutators to named actions?
  - When should I use mutators vs simple functions?
  - What packages use the mutator interfaces?
  - How do I create a data transformation pipeline?
audience:
  - developers
  - backend engineers
tags:
  - mutator
  - transformation
  - design-pattern
  - pipeline
llm_tags:
  - mutation-pattern
  - data-transformation
  - adapter-pattern
  - strategy-pattern
keywords:
  - phpnomad mutator
  - data transformation php
  - mutation pattern
  - MutationAdapter
  - MutationStrategy
related:
  - ../loader/introduction
  - ../di/introduction
see_also:
  - interfaces/introduction
  - traits/introduction
  - ../event/introduction
noindex: false
---

# Mutator

`phpnomad/mutator` provides **interfaces and traits for structured data transformation** in PHP applications. Instead of ad-hoc transformation functions scattered throughout your codebase, the mutator pattern gives you:

* **Consistent interfaces** — All transformations follow the same contract
* **Composable pipelines** — Chain mutations together via strategies
* **Separation of concerns** — Adapters handle conversion, mutators handle logic
* **Discoverability** — Objects can expose what mutations they support

---

## Key ideas at a glance

| Component | Purpose | Documentation |
|-----------|---------|---------------|
| **Mutator** | Stateful object that performs transformation via `mutate()` | [Interface docs](interfaces/mutator) |
| **MutatorHandler** | Functional interface: takes arguments, returns result directly | [Interface docs](interfaces/mutator-handler) |
| **MutationAdapter** | Converts between raw data and Mutator instances | [Interface docs](interfaces/mutation-adapter) |
| **MutationStrategy** | Attaches handlers to named actions for dynamic dispatch | [Interface docs](interfaces/mutation-strategy) |
| **HasMutations** | Interface for objects that expose their available mutations | [Interface docs](interfaces/has-mutations) |
| **CanMutateFromAdapter** | Trait that implements the adapter workflow | [Trait docs](traits/can-mutate-from-adapter) |

---

## Why this package exists

Data transformation is everywhere in PHP applications—sanitizing input, formatting output, validating data, converting between formats. Without a consistent pattern, you end up with:

* **Scattered transformation logic** — Functions and methods spread across the codebase
* **Inconsistent signatures** — Some transform in place, some return new values
* **Hard-to-test pipelines** — Transformation steps are tightly coupled
* **No discoverability** — Finding what transformations exist requires reading code

The mutator package provides **standardized contracts** that make transformations:

| Problem | Solution |
|---------|----------|
| Inconsistent APIs | `Mutator` and `MutatorHandler` define clear contracts |
| Coupled conversions | `MutationAdapter` separates data conversion from logic |
| Static dispatch | `MutationStrategy` enables dynamic, named transformations |
| Hidden capabilities | `HasMutations` lets objects advertise what they can do |

---

## Installation

```bash
composer require phpnomad/mutator
```

**Requirements:** PHP 7.4+

**Dependencies:** None (zero dependencies)

---

## The mutation workflow

When using the `CanMutateFromAdapter` trait, the transformation follows this flow:

```
Input data
    │
    ▼
┌───────────────────────────────┐
│  MutationAdapter              │
│  convertFromSource($args)     │
│  → creates Mutator instance   │
└───────────────────────────────┘
    │
    ▼
┌───────────────────────────────┐
│  Mutator                      │
│  mutate()                     │
│  → transforms internal state  │
└───────────────────────────────┘
    │
    ▼
┌───────────────────────────────┐
│  MutationAdapter              │
│  convertToResult($mutator)    │
│  → extracts output data       │
└───────────────────────────────┘
    │
    ▼
Output data
```

This separation means:
* **Adapters** handle I/O format conversion (JSON, arrays, objects, etc.)
* **Mutators** contain pure transformation logic
* Each component is testable in isolation

---

## Quick example

```php
use PHPNomad\Mutator\Interfaces\Mutator;
use PHPNomad\Mutator\Interfaces\MutationAdapter;
use PHPNomad\Mutator\Traits\CanMutateFromAdapter;

// 1. Define a mutator with transformation logic
class SlugifyMutator implements Mutator
{
    private string $input;
    private string $result;

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

// 2. Define an adapter for data conversion
class SlugAdapter implements MutationAdapter
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

// 3. Use the trait for automatic workflow
class SlugService
{
    use CanMutateFromAdapter;

    protected MutationAdapter $mutationAdapter;

    public function __construct()
    {
        $this->mutationAdapter = new SlugAdapter();
    }
}

// Usage
$service = new SlugService();
echo $service->mutate('Hello World!'); // "hello-world-"
```

---

## When to use mutators

Mutators are appropriate when:

| Scenario | Why mutators help |
|----------|-------------------|
| Complex transformations | Encapsulate multi-step logic in testable classes |
| Reusable transformations | Same mutator works across different contexts |
| Validation + transformation | Mutators can validate and transform in one pass |
| Dynamic pipelines | MutationStrategy enables runtime composition |
| Self-documenting code | HasMutations makes capabilities discoverable |

### Common use cases

* **Input validation and sanitization** — Clean user input before processing
* **Data format conversion** — Transform between DTOs, arrays, JSON
* **Business rule application** — Apply pricing rules, discounts, taxes
* **Content processing** — Markdown rendering, slug generation, text formatting
* **API response shaping** — Transform internal data for external consumption

---

## When NOT to use mutators

### Simple, one-off transformations

If you're just uppercasing a string once, a function call is fine:

```php
// Don't do this for trivial operations
$mutator = new UppercaseMutator($text);
$mutator->mutate();
$result = $mutator->getResult();

// Just do this
$result = strtoupper($text);
```

### Pure functions suffice

If your transformation has no state and no complex setup, a simple function or closure is cleaner.

### No reuse needed

If the transformation is used exactly once and is simple, inline it.

---

## Best practices

1. **Keep mutators focused** — Each mutator should do one thing well
2. **Make adapters responsible for conversion only** — Don't put business logic in adapters
3. **Use lazy initialization in strategies** — The `attach()` callable enables deferred instantiation
4. **Document what mutators do** — Especially for HasMutations, make capabilities clear

See the individual interface docs for detailed best practices:
- [Mutator best practices](interfaces/mutator#best-practices)
- [MutatorHandler best practices](interfaces/mutator-handler#best-practices)
- [MutationAdapter best practices](interfaces/mutation-adapter#best-practices)

---

## Relationship to other packages

### Packages that depend on mutator

| Package | How it uses mutator |
|---------|---------------------|
| [loader](/packages/loader/introduction) | Module initialization transformations |
| [wordpress-plugin](/packages/wordpress-plugin/introduction) | Data transformation in WordPress context |

### Related patterns

| Package | Relationship |
|---------|-------------|
| [event](/packages/event/introduction) | Events can trigger mutations; mutations can emit events |
| [di](/packages/di/introduction) | DI container can inject adapters and mutators |

---

## Package contents

### Interfaces

| Interface | Purpose |
|-----------|---------|
| [Mutator](interfaces/mutator) | Stateful transformation (modifies internal state) |
| [MutatorHandler](interfaces/mutator-handler) | Functional transformation (args in, result out) |
| [MutationAdapter](interfaces/mutation-adapter) | Bidirectional data conversion |
| [MutationStrategy](interfaces/mutation-strategy) | Register handlers to named actions |
| [HasMutations](interfaces/has-mutations) | Expose available mutations |

[View all interfaces →](interfaces/introduction)

### Traits

| Trait | Purpose |
|-------|---------|
| [CanMutateFromAdapter](traits/can-mutate-from-adapter) | Implements adapter workflow automatically |

[View all traits →](traits/introduction)

---

## Next steps

* **New to mutators?** Read [Mutator interface](interfaces/mutator) and [MutatorHandler](interfaces/mutator-handler) to understand the two transformation styles
* **Building pipelines?** See [MutationStrategy](interfaces/mutation-strategy) for dynamic dispatch
* **Using adapters?** Check [CanMutateFromAdapter trait](traits/can-mutate-from-adapter) to simplify your code
* **Need loader integration?** See [Loader](/packages/loader/introduction) which uses mutators extensively
