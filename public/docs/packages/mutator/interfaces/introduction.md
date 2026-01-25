---
id: mutator-interfaces-introduction
slug: docs/packages/mutator/interfaces/introduction
title: Mutator Interfaces Overview
doc_type: explanation
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: Overview of the five interfaces provided by the mutator package for implementing data transformation patterns.
llm_summary: >
  The mutator package provides five interfaces that work together for structured data transformation:
  Mutator (stateful transformation), MutatorHandler (functional transformation), MutationAdapter
  (bidirectional data conversion), MutationStrategy (named action registration), and HasMutations
  (capability discovery). Each interface serves a specific role in building composable, testable
  transformation pipelines.
questions_answered:
  - What interfaces does the mutator package provide?
  - How do the mutator interfaces relate to each other?
  - Which interface should I implement for my use case?
audience:
  - developers
  - backend engineers
tags:
  - interfaces
  - mutator
  - transformation
llm_tags:
  - interface-overview
  - mutator-interfaces
keywords:
  - mutator interfaces
  - transformation interfaces
  - MutationAdapter
  - MutationStrategy
related:
  - ../introduction
see_also:
  - mutator
  - mutator-handler
  - mutation-adapter
  - mutation-strategy
  - has-mutations
noindex: false
---

# Mutator Interfaces

The mutator package provides five interfaces that define contracts for structured data transformation. Each interface serves a specific role:

| Interface | Purpose | When to Use |
|-----------|---------|-------------|
| [Mutator](mutator) | Stateful transformation | Complex multi-step transformations with internal state |
| [MutatorHandler](mutator-handler) | Functional transformation | Simple transformations without state management |
| [MutationAdapter](mutation-adapter) | Bidirectional conversion | Separating data marshaling from transformation logic |
| [MutationStrategy](mutation-strategy) | Named action dispatch | Dynamic pipelines with registered transformations |
| [HasMutations](has-mutations) | Capability discovery | Objects that advertise their transformation capabilities |

---

## How the interfaces relate

```
                          ┌─────────────────────┐
                          │   MutationStrategy  │
                          │   (registers named  │
                          │    transformations) │
                          └─────────┬───────────┘
                                    │ attaches
                                    ▼
┌─────────────────┐         ┌─────────────────┐
│  HasMutations   │         │  MutatorHandler │
│  (advertises    │         │  (functional    │
│   capabilities) │         │   transform)    │
└─────────────────┘         └─────────────────┘
                                    │
                                    │ or uses
                                    ▼
                          ┌─────────────────────┐
                          │   MutationAdapter   │
                          │   (converts data    │
                          │    to/from Mutator) │
                          └─────────┬───────────┘
                                    │ creates/reads
                                    ▼
                          ┌─────────────────────┐
                          │      Mutator        │
                          │   (stateful         │
                          │    transformation)  │
                          └─────────────────────┘
```

---

## Choosing the right interface

**Start with [MutatorHandler](mutator-handler)** if:
- Your transformation is a simple input → output function
- No complex state management needed
- You want the simplest possible implementation

**Use [Mutator](mutator) + [MutationAdapter](mutation-adapter)** if:
- Transformation has multiple steps or phases
- You need to separate conversion logic from transformation logic
- Testing isolation is important

**Add [MutationStrategy](mutation-strategy)** if:
- You need to register transformations by name
- Building a plugin system or pipeline
- Runtime composition of transformations

**Implement [HasMutations](has-mutations)** if:
- Objects should advertise their capabilities
- Building discoverable APIs
- Self-documenting transformation systems

---

## Next steps

- [Mutator](mutator) — Stateful transformation interface
- [MutatorHandler](mutator-handler) — Functional transformation interface
- [MutationAdapter](mutation-adapter) — Bidirectional conversion interface
- [MutationStrategy](mutation-strategy) — Named action registration
- [HasMutations](has-mutations) — Capability discovery interface
