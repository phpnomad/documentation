---
id: logger-strategy-interface
slug: docs/packages/logger/interfaces/logger-strategy
title: LoggerStrategy Interface
doc_type: reference
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: The LoggerStrategy interface defines a PSR-3 compatible logging contract for PHPNomad applications.
llm_summary: >
  LoggerStrategy is the core logging interface in PHPNomad, providing PSR-3 compatible
  logging methods for all 8 standard log levels (emergency, alert, critical, error,
  warning, notice, info, debug) plus exception logging. Classes implementing this interface
  can be swapped without changing application code, enabling flexible logging destinations
  (files, databases, external services, null loggers for testing). Each method accepts
  a message string and optional context array for structured logging.
questions_answered:
  - What is the LoggerStrategy interface?
  - What methods does LoggerStrategy define?
  - How do I implement LoggerStrategy?
  - What parameters does each logging method accept?
  - How does logException work?
audience:
  - developers
  - backend engineers
tags:
  - logging
  - interface
  - psr-3
  - strategy-pattern
llm_tags:
  - logger-strategy
  - log-levels
  - exception-logging
keywords:
  - LoggerStrategy
  - logging interface
  - PSR-3
  - log levels
related:
  - ../introduction
  - ../traits/can-log-exception
see_also:
  - ../../database/introduction
  - ../../rest/interceptors/introduction
noindex: false
---

# LoggerStrategy Interface

**Namespace:** `PHPNomad\Logger\Interfaces`

`LoggerStrategy` defines the logging contract for PHPNomad applications. It follows PSR-3 conventions with methods for all 8 standard log levels plus exception logging.

---

## Interface Definition

```php
namespace PHPNomad\Logger\Interfaces;

use Exception;

interface LoggerStrategy
{
    public function emergency(string $message, array $context = []): void;
    public function alert(string $message, array $context = []): void;
    public function critical(string $message, array $context = []): void;
    public function error(string $message, array $context = []): void;
    public function warning(string $message, array $context = []): void;
    public function notice(string $message, array $context = []): void;
    public function info(string $message, array $context = []): void;
    public function debug(string $message, array $context = []): void;
    public function logException(
        Exception $e,
        string $message = '',
        array $context = [],
        string $level = null
    ): mixed;
}
```

---

## Methods

### Log Level Methods

All log level methods share the same signature:

```php
public function {level}(string $message, array $context = []): void
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `$message` | `string` | The log message |
| `$context` | `array` | Optional structured data to include with the message |

#### emergency()

Log when the system is completely unusable.

```php
$logger->emergency('Database corruption detected', [
    'table' => 'users',
    'corruption_type' => 'index_mismatch'
]);
```

**Use for:** Total system failure, data corruption, situations requiring immediate wake-up calls.

#### alert()

Log when immediate action is required.

```php
$logger->alert('Primary database unreachable', [
    'host' => $dbHost,
    'failover_active' => true
]);
```

**Use for:** Site down, database unavailable, disk full - situations requiring immediate human intervention.

#### critical()

Log critical conditions.

```php
$logger->critical('Payment gateway connection failed', [
    'gateway' => 'stripe',
    'error_code' => $errorCode
]);
```

**Use for:** Component unavailable, unexpected exceptions that affect core functionality.

#### error()

Log runtime errors that don't require immediate action.

```php
$logger->error('Failed to send notification email', [
    'user_id' => $userId,
    'email' => $email,
    'error' => $e->getMessage()
]);
```

**Use for:** Errors that should be investigated but don't halt the system.

#### warning()

Log exceptional occurrences that aren't errors.

```php
$logger->warning('Deprecated API endpoint called', [
    'endpoint' => '/api/v1/users',
    'replacement' => '/api/v2/users',
    'caller_ip' => $ip
]);
```

**Use for:** Deprecated API usage, poor practices, retry successes after failures.

#### notice()

Log normal but significant events.

```php
$logger->notice('Application configuration reloaded', [
    'config_file' => $configPath,
    'changes' => $changedKeys
]);
```

**Use for:** Service startup/shutdown, configuration changes, significant state changes.

#### info()

Log interesting events.

```php
$logger->info('User logged in', [
    'user_id' => $userId,
    'ip_address' => $ip,
    'user_agent' => $userAgent
]);
```

**Use for:** User actions, successful operations, routine events worth recording.

#### debug()

Log detailed debugging information.

```php
$logger->debug('SQL query executed', [
    'query' => $sql,
    'bindings' => $bindings,
    'duration_ms' => $duration
]);
```

**Use for:** Variable dumps, execution flow, detailed diagnostics. Typically disabled in production.

---

### logException()

Log an exception with configurable severity level.

```php
public function logException(
    Exception $e,
    string $message = '',
    array $context = [],
    string $level = null
): mixed;
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `$e` | `Exception` | The exception to log |
| `$message` | `string` | Optional additional message |
| `$context` | `array` | Optional structured context data |
| `$level` | `string\|null` | Log level to use (defaults to `critical`) |

**Example:**

```php
try {
    $this->processPayment($order);
} catch (PaymentException $e) {
    $this->logger->logException(
        $e,
        'Payment processing failed',
        ['order_id' => $order->getId()],
        LoggerLevel::Error
    );
    throw $e;
}
```

The default implementation (via [CanLogException trait](../traits/can-log-exception.md)) combines your message with the exception message and calls the appropriate level method.

---

## Log Level Severity

From most to least severe:

```
EMERGENCY  →  System is unusable
    ↓
  ALERT    →  Immediate action required
    ↓
CRITICAL   →  Critical conditions
    ↓
  ERROR    →  Runtime errors
    ↓
 WARNING   →  Exceptional but not errors
    ↓
 NOTICE    →  Normal but significant
    ↓
  INFO     →  Interesting events
    ↓
  DEBUG    →  Detailed debug info
```

---

## Implementing LoggerStrategy

### Basic Implementation

```php
use PHPNomad\Logger\Interfaces\LoggerStrategy;
use PHPNomad\Logger\Traits\CanLogException;
use PHPNomad\Logger\Enums\LoggerLevel;

class FileLogger implements LoggerStrategy
{
    use CanLogException;

    public function __construct(private string $logFile) {}

    public function emergency(string $message, array $context = []): void
    {
        $this->write(LoggerLevel::Emergency, $message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->write(LoggerLevel::Alert, $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->write(LoggerLevel::Critical, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write(LoggerLevel::Error, $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write(LoggerLevel::Warning, $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->write(LoggerLevel::Notice, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->write(LoggerLevel::Info, $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->write(LoggerLevel::Debug, $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextJson = empty($context) ? '' : ' ' . json_encode($context);
        $line = "[{$timestamp}] [{$level}] {$message}{$contextJson}\n";

        file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
```

### Null Logger for Testing

```php
class NullLogger implements LoggerStrategy
{
    use CanLogException;

    public function emergency(string $message, array $context = []): void {}
    public function alert(string $message, array $context = []): void {}
    public function critical(string $message, array $context = []): void {}
    public function error(string $message, array $context = []): void {}
    public function warning(string $message, array $context = []): void {}
    public function notice(string $message, array $context = []): void {}
    public function info(string $message, array $context = []): void {}
    public function debug(string $message, array $context = []): void {}
}
```

---

## Usage Examples

### Dependency Injection

```php
class OrderService
{
    public function __construct(private LoggerStrategy $logger) {}

    public function processOrder(Order $order): void
    {
        $this->logger->info('Processing order', [
            'order_id' => $order->getId(),
            'total' => $order->getTotal()
        ]);

        // Process...

        $this->logger->info('Order completed', [
            'order_id' => $order->getId()
        ]);
    }
}
```

### With Context Arrays

```php
// Use structured context instead of string interpolation
$this->logger->info('User action', [
    'user_id' => $userId,
    'action' => 'login',
    'ip_address' => $request->getClientIp(),
    'timestamp' => time()
]);
```

---

## See Also

- [CanLogException Trait](../traits/can-log-exception.md) - Default `logException()` implementation
- [Logger Package Overview](../introduction.md) - High-level documentation
- [Database Package](../../database/introduction.md) - Uses LoggerStrategy for query logging
- [REST Interceptors](../../rest/interceptors/introduction.md) - Request/response logging
