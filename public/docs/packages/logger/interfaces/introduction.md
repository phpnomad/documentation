---
id: logger-interfaces-introduction
slug: docs/packages/logger/interfaces/introduction
title: Logger Interfaces Overview
doc_type: reference
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: Overview of interfaces provided by the logger package.
llm_summary: >
  The phpnomad/logger package provides one interface: LoggerStrategy, which defines the
  contract for PSR-3 compatible logging throughout PHPNomad applications. This interface
  declares methods for all 8 standard log levels plus exception logging.
questions_answered:
  - What interfaces does the logger package provide?
  - What is the LoggerStrategy interface?
audience:
  - developers
  - backend engineers
tags:
  - logging
  - interfaces
  - reference
llm_tags:
  - logger-strategy
  - psr-3
keywords:
  - logger interfaces
  - LoggerStrategy
related:
  - ../introduction
  - ./logger-strategy
see_also:
  - ../traits/introduction
noindex: false
---

# Logger Interfaces

The logger package provides one core interface that defines the logging contract for PHPNomad applications.

---

## Available Interfaces

| Interface | Purpose |
|-----------|---------|
| [LoggerStrategy](./logger-strategy.md) | PSR-3 compatible logging contract with 8 log levels |

---

## Quick Reference

### LoggerStrategy

The primary interface for all logging operations:

```php
use PHPNomad\Logger\Interfaces\LoggerStrategy;

class MyService
{
    public function __construct(private LoggerStrategy $logger) {}

    public function doSomething(): void
    {
        $this->logger->info('Operation started');
    }
}
```

See [LoggerStrategy](./logger-strategy.md) for complete documentation.

---

## See Also

- [Logger Package Overview](../introduction.md) - High-level package documentation
- [Logger Traits](../traits/introduction.md) - Default implementations
