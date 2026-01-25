---
id: can-log-exception-trait
slug: docs/packages/logger/traits/can-log-exception
title: CanLogException Trait
doc_type: reference
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: The CanLogException trait provides a default implementation for logging exceptions in LoggerStrategy implementations.
llm_summary: >
  CanLogException is a trait that provides the default implementation of the logException()
  method for classes implementing LoggerStrategy. It combines the provided message with
  the exception message, defaults to critical severity level, and dynamically calls the
  appropriate log level method. This reduces boilerplate when creating custom logger
  implementations.
questions_answered:
  - What is the CanLogException trait?
  - How does CanLogException implement logException?
  - What is the default log level for exceptions?
  - How do I use CanLogException in my logger?
audience:
  - developers
  - backend engineers
tags:
  - logging
  - trait
  - exception-handling
llm_tags:
  - can-log-exception
  - exception-logging
  - default-implementation
keywords:
  - CanLogException
  - exception logging
  - logger trait
related:
  - ../introduction
  - ../interfaces/logger-strategy
see_also:
  - ../../database/introduction
noindex: false
---

# CanLogException Trait

**Namespace:** `PHPNomad\Logger\Traits`

`CanLogException` provides a default implementation of the `logException()` method for classes implementing `LoggerStrategy`. Use this trait to avoid writing boilerplate exception logging code.

---

## Trait Definition

```php
namespace PHPNomad\Logger\Traits;

use Exception;
use PHPNomad\Logger\Enums\LoggerLevel;

trait CanLogException
{
    public function logException(
        Exception $e,
        string $message = '',
        array $context = [],
        $level = null
    ) {
        if (!$level) {
            $level = LoggerLevel::Critical;
        }

        $this->$level(
            implode(' - ', [$message, $e->getMessage()]),
            $context
        );
    }
}
```

---

## Behavior

| Aspect | Behavior |
|--------|----------|
| Default level | `critical` if no level specified |
| Message format | Combines your message with exception message using ` - ` separator |
| Method dispatch | Dynamically calls the appropriate level method (`$this->$level(...)`) |

### Output Format

When you call:

```php
$logger->logException(
    $exception,
    'Payment failed',
    ['order_id' => 123],
    LoggerLevel::Error
);
```

The trait produces a log entry like:

```
[2026-01-25 10:30:45] [error] Payment failed - Card was declined {"order_id": 123}
```

---

## Usage

### Basic Usage

```php
use PHPNomad\Logger\Interfaces\LoggerStrategy;
use PHPNomad\Logger\Traits\CanLogException;

class FileLogger implements LoggerStrategy
{
    use CanLogException;

    // Implement the 8 log level methods...
    public function emergency(string $message, array $context = []): void
    {
        $this->write('emergency', $message, $context);
    }

    // ... other level methods

    private function write(string $level, string $message, array $context): void
    {
        // Write to file
    }
}
```

With the trait, `logException()` is automatically available:

```php
$logger = new FileLogger('/var/log/app.log');

try {
    riskyOperation();
} catch (Exception $e) {
    $logger->logException($e, 'Operation failed');
}
```

### Specifying Log Level

```php
use PHPNomad\Logger\Enums\LoggerLevel;

// Default: critical
$logger->logException($e, 'Something went wrong');

// Explicit level
$logger->logException($e, 'User input error', [], LoggerLevel::Warning);
$logger->logException($e, 'Database error', [], LoggerLevel::Error);
$logger->logException($e, 'Fatal failure', [], LoggerLevel::Emergency);
```

### With Context

```php
try {
    $this->processOrder($order);
} catch (PaymentException $e) {
    $this->logger->logException(
        $e,
        'Payment processing failed',
        [
            'order_id' => $order->getId(),
            'customer_id' => $order->getCustomerId(),
            'amount' => $order->getTotal()
        ],
        LoggerLevel::Error
    );
}
```

---

## Overriding the Implementation

If you need different behavior, you can override the method in your logger class:

```php
class CustomLogger implements LoggerStrategy
{
    use CanLogException {
        logException as protected defaultLogException;
    }

    public function logException(
        Exception $e,
        string $message = '',
        array $context = [],
        $level = null
    ) {
        // Add stack trace to context
        $context['stack_trace'] = $e->getTraceAsString();
        $context['exception_class'] = get_class($e);
        $context['file'] = $e->getFile();
        $context['line'] = $e->getLine();

        $this->defaultLogException($e, $message, $context, $level);
    }
}
```

---

## Requirements

The trait requires that your class implements all 8 log level methods from `LoggerStrategy`:

- `emergency(string $message, array $context = []): void`
- `alert(string $message, array $context = []): void`
- `critical(string $message, array $context = []): void`
- `error(string $message, array $context = []): void`
- `warning(string $message, array $context = []): void`
- `notice(string $message, array $context = []): void`
- `info(string $message, array $context = []): void`
- `debug(string $message, array $context = []): void`

The trait dynamically calls `$this->$level()`, so all level methods must exist.

---

## See Also

- [LoggerStrategy Interface](../interfaces/logger-strategy.md) - The interface this trait helps implement
- [Logger Package Overview](../introduction.md) - High-level documentation
