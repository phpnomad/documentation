---
id: enum-polyfill-introduction
slug: docs/packages/enum-polyfill/introduction
title: Enum Polyfill Package
doc_type: explanation
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: The enum-polyfill package provides PHP 8.1-style enum methods to regular classes using constants, enabling backward-compatible enum functionality.
llm_summary: >
  phpnomad/enum-polyfill provides the Enum trait that gives PHP classes enum-like behavior using
  class constants. It provides methods matching PHP 8.1's native enum API: cases(), from(),
  tryFrom(), getValues(), and isValid(). The trait uses singleton pattern (via WithInstance) to
  cache reflection results for performance. Classes using this trait define constants as enum
  values and get automatic validation, iteration, and safe value retrieval. Commonly used for
  HTTP methods, CRUD action types, session contexts, and other fixed sets of values. Works on
  PHP 7.4+ and provides forward-compatible syntax for projects targeting older PHP versions.
questions_answered:
  - What is the enum-polyfill package?
  - How do I create enums in PHPNomad for older PHP versions?
  - When should I use enum-polyfill vs native PHP enums?
audience:
  - developers
  - backend engineers
tags:
  - enum
  - polyfill
  - trait
  - backward-compatibility
llm_tags:
  - enum-trait
  - cases-method
  - from-method
  - tryFrom-method
  - php-compatibility
keywords:
  - phpnomad enum
  - php enum polyfill
  - enum trait php
  - backward compatible enum
  - php 7.4 enum
related:
  - ../singleton/introduction
  - ../auth/introduction
  - ../http/introduction
see_also:
  - ../cache/introduction
  - ../rest/introduction
noindex: false
---

# Enum Polyfill

`phpnomad/enum-polyfill` provides **PHP 8.1-style enum functionality for older PHP versions**. It consists of a single trait—`Enum`—that can be added to any class with constants to gain enum-like behavior.

At its core:

* **API compatibility** — Methods match PHP 8.1's native enum API (`cases()`, `from()`, `tryFrom()`)
* **Validation** — Check if values are valid enum members with `isValid()`
* **Performance** — Reflection results cached via singleton pattern
* **Zero migration path** — When upgrading to PHP 8.1+, switch to native enums with minimal changes

---

## Key ideas at a glance

| Component | Purpose |
|-----------|---------|
| [Enum trait](./traits/enum.md) | Add to any class with constants to get enum behavior |
| `cases()` / `getValues()` | Returns all possible enum values |
| `from($value)` | Gets value or throws exception if invalid |
| `tryFrom($value)` | Gets value or returns null if invalid |
| `isValid($value)` | Check if a value is a valid enum member |

---

## Why this package exists

PHP 8.1 introduced native enums, but many projects still support older PHP versions. Without a polyfill, developers must:

| Problem | Without enum-polyfill | With enum-polyfill |
|---------|----------------------|-------------------|
| Validation | Write custom validation per "enum" | `Status::isValid($value)` |
| Listing values | Manual array or reflection | `Status::cases()` |
| Safe retrieval | Custom try/catch everywhere | `Status::tryFrom($value)` |
| Migration path | Rewrite when upgrading PHP | Swap trait for native enum |

This package provides a **forward-compatible API** that mirrors PHP 8.1 enums, making future migration straightforward.

---

## Installation

```bash
composer require phpnomad/enum-polyfill
```

**Requirements:** PHP 7.4+

**Dependencies:** `phpnomad/singleton`

---

## Basic usage

Define a class with constants and add the trait:

```php
use PHPNomad\Enum\Traits\Enum;

class Status
{
    use Enum;

    public const Active = 'active';
    public const Pending = 'pending';
    public const Inactive = 'inactive';
}
```

Now use it like a PHP 8.1 enum:

```php
// Get all possible values
$statuses = Status::cases();
// ['active', 'pending', 'inactive']

// Validate a value
if (Status::isValid($userInput)) {
    // Safe to use
}

// Get value or null
$status = Status::tryFrom($userInput);
if ($status !== null) {
    // Valid value
}

// Get value or throw exception
try {
    $status = Status::from($userInput);
} catch (UnexpectedValueException $e) {
    // Invalid value
}
```

See [Enum trait](./traits/enum.md) for complete API documentation.

---

## When to use this package

| Scenario | Recommendation |
|----------|---------------|
| PHP 7.4 / 8.0 project | Use enum-polyfill |
| PHP 8.1+ project | Consider native enums |
| Library supporting PHP 7.4+ | Use enum-polyfill for compatibility |
| Need custom enum methods | Use enum-polyfill (more flexible than native) |
| Strict type safety needed | Native PHP 8.1 enums are stronger |

### Advantages over native enums

* Works on PHP 7.4+
* Can add arbitrary methods to enum classes
* Constant values can be any type

### Advantages of native enums

* True type safety (function accepts `Status`, not `string`)
* Better IDE support
* Pattern matching with `match` expressions
* Built into the language

---

## When NOT to use this package

### You're on PHP 8.1+ exclusively

If you don't need to support older PHP versions, native enums are cleaner:

```php
// Native PHP 8.1 enum
enum Status: string
{
    case Active = 'active';
    case Pending = 'pending';
    case Inactive = 'inactive';
}

// Type-safe function
function setStatus(Status $status): void
{
    // $status is guaranteed to be a valid Status
}

setStatus(Status::Active); // OK
setStatus('active');       // Error - type mismatch
```

### You need true type safety

The polyfill returns the constant values (strings, integers, etc.), not typed objects:

```php
// With polyfill - no type safety
function setStatus(string $status): void
{
    if (!Status::isValid($status)) {
        throw new InvalidArgumentException();
    }
}

setStatus('typo'); // Compiles fine, fails at runtime
```

---

## Package contents

### Traits

| Trait | Description |
|-------|-------------|
| [Enum](./traits/enum.md) | Provides enum-like behavior to classes with constants |

See [Traits Overview](./traits/introduction.md) for details.

---

## Migration to native enums

When you upgrade to PHP 8.1+, converting is straightforward:

```php
// Before (polyfill)
use PHPNomad\Enum\Traits\Enum;

class Status
{
    use Enum;

    public const Active = 'active';
    public const Pending = 'pending';
    public const Inactive = 'inactive';
}

$status = Status::from('active'); // 'active'
```

```php
// After (native)
enum Status: string
{
    case Active = 'active';
    case Pending = 'pending';
    case Inactive = 'inactive';
}

$status = Status::from('active'); // Status::Active
```

The main difference: native enums return enum instances, while the polyfill returns the raw values. Update call sites accordingly.

---

## Relationship to other packages

### Dependencies

| Package | Relationship |
|---------|-------------|
| [singleton](../singleton/introduction.md) | Uses `WithInstance` trait for caching |

### Packages that use enum-polyfill

| Package | How it uses enum-polyfill |
|---------|--------------------------|
| [auth](../auth/introduction.md) | `ActionTypes`, `SessionContexts` enums |
| [http](../http/introduction.md) | `Method` enum for HTTP verbs |
| [cache](../cache/introduction.md) | `Operation` enum for CRUD operations |
| [rest](../rest/introduction.md) | `BasicTypes` enum |
| wordpress-plugin | Various status and type enums |

---

## Next steps

* **[Enum Trait](./traits/enum.md)** — Complete API reference
* **[Singleton Package](../singleton/introduction.md)** — Understand the caching mechanism
* **[HTTP Package](../http/introduction.md)** — See Method enum in action
* **[Auth Package](../auth/introduction.md)** — See ActionTypes enum
