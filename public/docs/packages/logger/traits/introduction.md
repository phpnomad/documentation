---
id: logger-traits-introduction
slug: docs/packages/logger/traits/introduction
title: Logger Traits Overview
doc_type: reference
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: Overview of traits provided by the logger package.
llm_summary: >
  The phpnomad/logger package provides one trait: CanLogException, which offers a default
  implementation for the logException method defined in LoggerStrategy. This trait can be
  used by any class implementing LoggerStrategy to get exception logging behavior without
  writing it from scratch.
questions_answered:
  - What traits does the logger package provide?
  - What is the CanLogException trait?
audience:
  - developers
  - backend engineers
tags:
  - logging
  - traits
  - reference
llm_tags:
  - can-log-exception
  - exception-logging
keywords:
  - logger traits
  - CanLogException
related:
  - ../introduction
  - ./can-log-exception
see_also:
  - ../interfaces/introduction
noindex: false
---

# Logger Traits

The logger package provides one trait that helps when implementing the LoggerStrategy interface.

---

## Available Traits

| Trait | Purpose |
|-------|---------|
| [CanLogException](./can-log-exception.md) | Default implementation for exception logging |

---

## Quick Reference

### CanLogException

Provides a default `logException()` implementation:

```php
use PHPNomad\Logger\Interfaces\LoggerStrategy;
use PHPNomad\Logger\Traits\CanLogException;

class MyLogger implements LoggerStrategy
{
    use CanLogException;

    // Only need to implement the 8 level methods
    // logException() is provided by the trait
}
```

See [CanLogException](./can-log-exception.md) for complete documentation.

---

## See Also

- [Logger Package Overview](../introduction.md) - High-level package documentation
- [Logger Interfaces](../interfaces/introduction.md) - Interface documentation
