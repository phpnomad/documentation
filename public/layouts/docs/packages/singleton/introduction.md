---
id: singleton-introduction
slug: docs/packages/singleton/introduction
title: Singleton Package
doc_type: explanation
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: The singleton package provides a reusable trait for implementing the singleton pattern in PHP classes.
llm_summary: >
  phpnomad/singleton provides the WithInstance trait that gives any PHP class singleton behavior.
  The trait maintains a static instance and provides lazy initialization via the instance() method.
  Uses late static binding (static::) to support inheritance hierarchies where each subclass
  maintains its own singleton. Commonly used for configuration managers, loggers, and service
  locators. Consider using dependency injection containers for better testability in most cases.
questions_answered:
  - What is the singleton package?
  - How do I make a class a singleton in PHPNomad?
  - How does the WithInstance trait work?
  - Can singleton classes be extended?
  - How do I test classes that use singletons?
  - When should I use singletons vs dependency injection?
  - What packages use the singleton trait?
audience:
  - developers
  - backend engineers
tags:
  - singleton
  - design-pattern
  - trait
llm_tags:
  - singleton-pattern
  - with-instance
  - lazy-initialization
keywords:
  - phpnomad singleton
  - singleton pattern php
  - WithInstance trait
  - static instance
related:
  - ../di/introduction
  - ../enum-polyfill/introduction
see_also:
  - ../logger/introduction
  - ../core/introduction
noindex: false
---

# Singleton

`phpnomad/singleton` provides a **reusable implementation of the singleton pattern** for PHP applications. It consists of a single trait—`WithInstance`—that can be added to any class to ensure only one instance exists throughout your application's lifecycle.

At its core:

* **Lazy initialization** — The instance is created only when first requested
* **Late static binding** — Each class in an inheritance hierarchy maintains its own singleton
* **Zero configuration** — Just add the trait and call `instance()`

---

## Key ideas at a glance

* **WithInstance** — A trait that provides singleton behavior to any class
* **`instance()` method** — Returns the singleton instance, creating it on first call
* **`$instance` property** — Protected static property storing the singleton
* **Late static binding** — Uses `static::` so subclasses get their own instances

---

## Why this package exists

The singleton pattern solves a specific problem: ensuring a class has exactly one instance while providing global access to it. Without a standardized implementation, developers often:

* Rewrite the same boilerplate in every singleton class
* Make mistakes with static binding (`self::` vs `static::`)
* Forget to handle inheritance correctly
* Create inconsistent implementations across a codebase

This package provides a **tested, consistent implementation** that handles these edge cases correctly.

---

## Installation

```bash
composer require phpnomad/singleton
```

**Requirements:** PHP 7.4+

**Dependencies:** None (zero dependencies)

---

## The singleton lifecycle

When you call `instance()` on a class using the `WithInstance` trait:

```
First call to MyClass::instance()
         │
         ▼
┌─────────────────────────┐
│ Check: is $instance set?│
└─────────────────────────┘
         │
    No ──┴── Yes
    │         │
    ▼         │
┌─────────────┐    │
│ Create new  │    │
│ instance    │    │
│ new static()│    │
└─────────────┘    │
    │              │
    ▼              │
┌─────────────┐    │
│ Store in    │    │
│ $instance   │    │
└─────────────┘    │
    │              │
    └──────┬───────┘
           │
           ▼
    Return $instance
```

All subsequent calls skip instance creation and return the stored instance immediately.

---

## Basic usage

Add the trait to any class:

```php
use PHPNomad\Singleton\Traits\WithInstance;

class ConfigManager
{
    use WithInstance;

    private array $settings = [];

    public function set(string $key, mixed $value): void
    {
        $this->settings[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }
}
```

Access the singleton from anywhere:

```php
// In your bootstrap
ConfigManager::instance()->set('debug', true);
ConfigManager::instance()->set('timezone', 'UTC');

// Later, in a controller
$debug = ConfigManager::instance()->get('debug'); // true

// In a service
$timezone = ConfigManager::instance()->get('timezone'); // 'UTC'
```

Every call to `instance()` returns the same object.

---

## How it works

The trait implementation is minimal:

```php
trait WithInstance
{
    protected static $instance;

    public static function instance()
    {
        if (!isset(static::$instance)) {
            static::$instance = new static;
        }

        return static::$instance;
    }
}
```

Key implementation details:

| Aspect | Implementation | Why |
|--------|----------------|-----|
| Property visibility | `protected static` | Allows subclasses to access/reset |
| Static binding | `static::$instance` | Each class gets its own instance |
| Instantiation | `new static` | Creates the actual subclass, not the trait user |
| Initialization | Lazy (on first call) | No overhead if never used |

---

## Inheritance behavior

Because the trait uses `static::` (late static binding), each class in an inheritance hierarchy maintains its own singleton:

```php
class BaseService
{
    use WithInstance;

    protected string $name = 'base';

    public function getName(): string
    {
        return $this->name;
    }
}

class UserService extends BaseService
{
    protected string $name = 'users';
}

class OrderService extends BaseService
{
    protected string $name = 'orders';
}

// Each class has its own singleton instance
$base = BaseService::instance();    // BaseService object
$users = UserService::instance();   // UserService object
$orders = OrderService::instance(); // OrderService object

$base->getName();   // 'base'
$users->getName();  // 'users'
$orders->getName(); // 'orders'

// These are all different objects
$base !== $users;   // true
$users !== $orders; // true
```

This is the correct behavior—if you had used `self::` instead of `static::`, all subclasses would share the parent's instance, which is almost never what you want.

---

## When to use singletons

Singletons are appropriate when:

* **Exactly one instance must exist** — Configuration, logging, connection pools
* **Global access is genuinely needed** — The instance is used across many unrelated parts of the system
* **State must persist** — The instance maintains state that shouldn't reset
* **Resource management** — Controlling access to a shared resource like a file handle or connection

### Common use cases

| Use Case | Why Singleton |
|----------|---------------|
| Configuration manager | One source of truth for settings |
| Logger | Consistent logging across the application |
| Database connection pool | Manage limited connections |
| Cache manager | Single cache instance with shared state |
| Event dispatcher | Central hub for all events |

---

## When NOT to use singletons

Singletons are often overused. Avoid them when:

### Testing is important

Singletons carry state between tests, causing flaky tests:

```php
// Test 1 sets state
ConfigManager::instance()->set('mode', 'test');

// Test 2 unexpectedly has that state
$mode = ConfigManager::instance()->get('mode'); // 'test' - leaked from Test 1!
```

### Dependency injection is available

If you're using a DI container, prefer container-managed singletons:

```php
// Instead of this (hard to test, hidden dependency)
class UserController
{
    public function index()
    {
        $users = Database::instance()->query('SELECT * FROM users');
    }
}

// Do this (explicit dependency, testable)
class UserController
{
    public function __construct(private Database $db) {}

    public function index()
    {
        $users = $this->db->query('SELECT * FROM users');
    }
}
```

### The "single instance" requirement isn't real

Ask yourself: does this *really* need to be a singleton, or is it just convenient? Often, passing instances through constructors (dependency injection) is cleaner.

---

## Best practices

### 1. Keep singleton classes focused

Singletons should do one thing. If your singleton is managing config *and* logging *and* caching, split it up.

### 2. Make constructors private or protected

Prevent direct instantiation to enforce the singleton pattern:

```php
class Logger
{
    use WithInstance;

    private function __construct()
    {
        // Initialize logger
    }
}

// This is now impossible:
$logger = new Logger(); // Error: private constructor
```

### 3. Consider immutability

If possible, make singleton state immutable after initialization:

```php
class Config
{
    use WithInstance;

    private array $settings;
    private bool $locked = false;

    public function load(array $settings): void
    {
        if ($this->locked) {
            throw new RuntimeException('Config is locked');
        }
        $this->settings = $settings;
        $this->locked = true;
    }

    public function get(string $key): mixed
    {
        return $this->settings[$key] ?? null;
    }
}
```

### 4. Document singleton usage

Make it clear in your class docblock that it's a singleton:

```php
/**
 * Application configuration manager.
 *
 * This is a singleton - use Config::instance() to access.
 */
class Config
{
    use WithInstance;
}
```

---

## Testing with singletons

Singletons persist across tests, which can cause issues. Here are strategies to handle this:

### Strategy 1: Testable subclass

Create a test-specific subclass that can reset the instance:

```php
class TestableConfig extends Config
{
    public static function resetInstance(): void
    {
        static::$instance = null;
    }
}

// In your test
protected function setUp(): void
{
    TestableConfig::resetInstance();
}
```

### Strategy 2: Reflection

Reset any singleton using reflection:

```php
function resetSingleton(string $class): void
{
    $reflection = new ReflectionClass($class);
    $property = $reflection->getProperty('instance');
    $property->setAccessible(true);
    $property->setValue(null, null);
}

// In your test
protected function setUp(): void
{
    resetSingleton(Config::class);
}
```

### Strategy 3: Dependency injection

The best solution is often to avoid calling `instance()` directly in the code you're testing:

```php
// Instead of this (hard to test)
class UserService
{
    public function getUsers(): array
    {
        return Database::instance()->query('...');
    }
}

// Do this (easy to test)
class UserService
{
    public function __construct(private Database $db) {}

    public function getUsers(): array
    {
        return $this->db->query('...');
    }
}

// In production, inject the singleton
$service = new UserService(Database::instance());

// In tests, inject a mock
$service = new UserService($mockDatabase);
```

---

## Integration with dependency injection

The PHPNomad [DI container](/packages/di/introduction) can manage singleton instances while maintaining testability:

```php
use PHPNomad\Di\Container;

$container = new Container();

// Register as a singleton in the container
$container->singleton(Logger::class, function() {
    return new Logger();
});

// The container ensures only one instance exists
$logger1 = $container->get(Logger::class);
$logger2 = $container->get(Logger::class);

$logger1 === $logger2; // true
```

This approach gives you singleton behavior with:
* Explicit dependencies (visible in constructors)
* Easy mocking in tests
* Centralized configuration

---

## Relationship to other packages

### Packages that depend on singleton

| Package | How it uses singleton |
|---------|----------------------|
| [enum-polyfill](/packages/enum-polyfill/introduction) | Enum instances use singleton pattern |
| [logger](/packages/logger/introduction) | Logger strategies can be singletons |
| [database](/packages/database/introduction) | Connection management |
| [core](/packages/core/introduction) | Core framework services |

### Related packages

| Package | Relationship |
|---------|-------------|
| [di](/packages/di/introduction) | Alternative approach via container-managed singletons |
| [facade](/packages/facade/introduction) | Facades often wrap singleton services |

---

## API reference

### WithInstance Trait

**Namespace:** `PHPNomad\Singleton\Traits`

#### Properties

| Property | Type | Visibility | Description |
|----------|------|------------|-------------|
| `$instance` | `static` | `protected static` | Stores the singleton instance for the class |

#### Methods

| Method | Signature | Returns | Description |
|--------|-----------|---------|-------------|
| `instance` | `public static function instance()` | `static` | Returns the singleton instance, creating it on first call |

---

## Next steps

* **Need dependency injection?** See [DI Container](/packages/di/introduction) for container-managed singletons
* **Building enums?** See [Enum Polyfill](/packages/enum-polyfill/introduction) which uses this trait
* **Setting up logging?** See [Logger](/packages/logger/introduction) for logging strategies
