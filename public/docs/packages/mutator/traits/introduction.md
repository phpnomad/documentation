---
id: mutator-traits-introduction
slug: docs/packages/mutator/traits/introduction
title: Mutator Traits Overview
doc_type: explanation
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: Overview of the CanMutateFromAdapter trait that automates the adapter-based mutation workflow.
llm_summary: >
  The mutator package provides one trait: CanMutateFromAdapter. This trait implements the standard
  adapter workflow (convert from source, mutate, convert to result) automatically. Classes using
  this trait must have a $mutationAdapter property. The trait provides a mutate(...$args) method
  that handles the full transformation flow.
questions_answered:
  - What traits does the mutator package provide?
  - How does CanMutateFromAdapter work?
  - What are the requirements for using the trait?
audience:
  - developers
  - backend engineers
tags:
  - traits
  - mutator
  - transformation
llm_tags:
  - trait-overview
  - mutator-traits
keywords:
  - mutator traits
  - CanMutateFromAdapter
related:
  - ../introduction
see_also:
  - can-mutate-from-adapter
  - ../interfaces/mutation-adapter
noindex: false
---

# Mutator Traits

The mutator package provides one trait that simplifies the adapter-based mutation workflow.

| Trait | Purpose | Requirements |
|-------|---------|--------------|
| [CanMutateFromAdapter](can-mutate-from-adapter) | Automates convert → mutate → convert workflow | `$mutationAdapter` property |

---

## The adapter workflow

When using `MutationAdapter` with `Mutator`, you typically follow this flow:

```
Input → convertFromSource() → Mutator → mutate() → convertToResult() → Output
```

The `CanMutateFromAdapter` trait implements this entire flow in a single `mutate()` method.

---

## Quick example

```php
use PHPNomad\Mutator\Traits\CanMutateFromAdapter;
use PHPNomad\Mutator\Interfaces\MutationAdapter;

class SlugService
{
    use CanMutateFromAdapter;

    protected MutationAdapter $mutationAdapter;

    public function __construct()
    {
        $this->mutationAdapter = new SlugAdapter();
    }
}

// Usage - the trait handles the full workflow
$service = new SlugService();
$slug = $service->mutate('Hello World!'); // "hello-world-"
```

---

## Next steps

- [CanMutateFromAdapter](can-mutate-from-adapter) — Full trait documentation
- [MutationAdapter](../interfaces/mutation-adapter) — The adapter interface the trait works with
