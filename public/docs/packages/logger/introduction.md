---
id: logger-introduction
slug: docs/packages/logger/introduction
title: Logger Package
doc_type: explanation
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: The logger package provides a PSR-3 compatible logging interface for consistent logging across PHPNomad applications.
llm_summary: >
  phpnomad/logger provides the LoggerStrategy interface and CanLogException trait for consistent
  logging throughout PHPNomad applications. The interface follows PSR-3 conventions with 8 log
  levels (emergency, alert, critical, error, warning, notice, info, debug) plus exception logging.
  The CanLogException trait provides a default implementation for logging exceptions with
  configurable severity levels. This is an abstraction package - it defines the logging contract
  but implementations come from integration packages or application code. Used extensively by
  database, cache, REST, datastore packages and throughout the framework for error tracking,
  debugging, and audit logging.
questions_answered:
  - What is the logger package?
  - How do I implement logging in PHPNomad?
  - What log levels are available?
  - How do I log exceptions?
audience:
  - developers
  - backend engineers
  - devops
tags:
  - logging
  - psr-3
  - interface
  - strategy-pattern
llm_tags:
  - logger-strategy
  - log-levels
  - exception-logging
  - can-log-exception
keywords:
  - phpnomad logger
  - logging interface
  - psr-3 logging
  - log levels php
  - exception logging
related:
  - ../database/introduction
  - ../cache/introduction
  - ../singleton/introduction
see_also:
  - ../rest/interceptors/introduction
  - ../datastore/introduction
noindex: false
---

# Logger

`phpnomad/logger` provides a **PSR-3 compatible logging interface** for consistent logging across PHPNomad applications. It defines the logging contract—implementations are provided by integration packages or your application code.

At its core:

* **PSR-3 compatible** — Eight standard log levels matching the widely-adopted standard
* **Strategy pattern** — Swap logging implementations without changing application code
* **Exception logging** — Built-in support for logging exceptions with stack traces
* **Zero dependencies** — Pure abstraction with no external requirements

---

## Key ideas at a glance

| Component | Purpose |
|-----------|---------|
| [LoggerStrategy](./interfaces/logger-strategy.md) | Interface defining all logging methods |
| [CanLogException](./traits/can-log-exception.md) | Trait providing default exception logging |
| LoggerLevel | Constants for the 8 standard log levels |

---

## Why this package exists

Applications need consistent logging, but logging destinations vary:

| Environment | Typical destination |
|-------------|-------------------|
| Development | Console/stdout |
| Production | File, database, or log service |
| WordPress | `error_log()` or WP debug.log |
| Testing | In-memory or null logger |

Without a common interface, code becomes tied to specific loggers. With LoggerStrategy, you can swap implementations without changing application code.

---

## Installation

```bash
composer require phpnomad/logger
```

**Requirements:** PHP 7.4+

**Dependencies:** None (zero dependencies)

---

## Log levels

The eight standard log levels, from most to least severe:

```
SEVERITY HIERARCHY (highest to lowest)
══════════════════════════════════════

 EMERGENCY   System is unusable
     │       (total failure, data corruption)
     ▼
   ALERT     Immediate action required
     │       (site down, database unavailable)
     ▼
 CRITICAL    Critical conditions
     │       (component unavailable, unexpected exception)
     ▼
   ERROR     Runtime errors
     │       (errors that don't require immediate action)
     ▼
  WARNING    Exceptional occurrences that aren't errors
     │       (deprecated APIs, poor API usage)
     ▼
  NOTICE     Normal but significant events
     │       (startup, shutdown, config changes)
     ▼
   INFO      Interesting events
     │       (user actions, SQL queries, API calls)
     ▼
   DEBUG     Detailed debug information
             (variable dumps, execution flow)
```

---

## Basic usage

Inject `LoggerStrategy` and call the appropriate level method:

```php
use PHPNomad\Logger\Interfaces\LoggerStrategy;

class OrderService
{
    public function __construct(private LoggerStrategy $logger) {}

    public function processOrder(Order $order): void
    {
        $this->logger->info('Processing order', [
            'order_id' => $order->getId(),
            'customer' => $order->getCustomerId()
        ]);

        try {
            $this->chargePayment($order);
        } catch (PaymentException $e) {
            $this->logger->error('Payment failed', [
                'order_id' => $order->getId(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
```

See [LoggerStrategy](./interfaces/logger-strategy.md) for complete API documentation.

---

## When to use each level

| Level | Use when... | Examples |
|-------|-------------|----------|
| Emergency | System is completely unusable | Data corruption, total failure |
| Alert | Immediate human action required | Database down, disk full |
| Critical | Critical component failed | Payment gateway unreachable |
| Error | Something failed but system continues | Failed API call, validation error |
| Warning | Something unexpected but not an error | Deprecated API used, slow query |
| Notice | Normal but notable events | Service started, config reloaded |
| Info | Routine operations worth recording | User login, order placed |
| Debug | Detailed debugging info | Variable dumps, SQL queries |

---

## Best practices

### Use structured context

```php
// Bad - string interpolation
$this->logger->info("Order {$orderId} created by user {$userId}");

// Good - structured context
$this->logger->info('Order created', [
    'order_id' => $orderId,
    'user_id' => $userId
]);
```

### Don't log sensitive data

```php
// Bad - logging passwords
$this->logger->info('User login', ['password' => $password]);

// Good - redact sensitive fields
$this->logger->info('User login', ['username' => $username]);
```

### Log at appropriate levels

```php
$this->logger->error('User not found');  // Bad - not an error
$this->logger->info('User not found');   // Good - expected behavior
```

---

## Package contents

### Interfaces

| Interface | Description |
|-----------|-------------|
| [LoggerStrategy](./interfaces/logger-strategy.md) | PSR-3 compatible logging contract |

See [Interfaces Overview](./interfaces/introduction.md) for details.

### Traits

| Trait | Description |
|-------|-------------|
| [CanLogException](./traits/can-log-exception.md) | Default `logException()` implementation |

See [Traits Overview](./traits/introduction.md) for details.

### Enums

| Enum | Description |
|------|-------------|
| LoggerLevel | Constants for all 8 log levels |

---

## Relationship to other packages

### Packages that use logger

| Package | How it uses LoggerStrategy |
|---------|---------------------------|
| [database](../database/introduction.md) | Logs query errors and operations |
| [cache](../cache/introduction.md) | Logs cache misses and errors |
| [rest](../rest/introduction.md) | Request/response logging via interceptors |
| [datastore](../datastore/introduction.md) | Operation logging in decorators |
| [facade](../facade/introduction.md) | LogService facade wraps LoggerStrategy |

### Related packages

| Package | Relationship |
|---------|-------------|
| [singleton](../singleton/introduction.md) | Logger instances often use singleton pattern |
| [di](../di/introduction.md) | Logger typically registered in container |

---

## Next steps

* **[LoggerStrategy Interface](./interfaces/logger-strategy.md)** — Complete interface documentation
* **[CanLogException Trait](./traits/can-log-exception.md)** — Default exception logging
* **[Database Package](../database/introduction.md)** — See query logging in action
* **[REST Interceptors](../rest/interceptors/introduction.md)** — Request/response logging
