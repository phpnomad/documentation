---
id: enum-polyfill-traits-introduction
slug: docs/packages/enum-polyfill/traits/introduction
title: Enum Polyfill Traits Overview
doc_type: reference
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: Overview of traits provided by the enum-polyfill package.
llm_summary: >
  The phpnomad/enum-polyfill package provides one trait: Enum, which gives any PHP class with
  constants enum-like behavior including cases(), from(), tryFrom(), and isValid() methods.
  This enables PHP 8.1-style enum functionality on older PHP versions.
questions_answered:
  - What traits does the enum-polyfill package provide?
  - What is the Enum trait?
audience:
  - developers
  - backend engineers
tags:
  - enum
  - traits
  - reference
llm_tags:
  - enum-trait
  - php-compatibility
keywords:
  - enum traits
  - Enum trait
related:
  - ../introduction
  - ./enum
see_also:
  - ../../singleton/introduction
noindex: false
---

# Enum Polyfill Traits

The enum-polyfill package provides one trait that enables PHP 8.1-style enum functionality on older PHP versions.

---

## Available Traits

| Trait | Purpose |
|-------|---------|
| [Enum](./enum.md) | Adds enum-like behavior to classes with constants |

---

## Quick Reference

### Enum Trait

Adds `cases()`, `from()`, `tryFrom()`, and `isValid()` methods to any class:

```php
use PHPNomad\Enum\Traits\Enum;

class Status
{
    use Enum;

    public const Active = 'active';
    public const Pending = 'pending';
    public const Inactive = 'inactive';
}

// Now use it like a PHP 8.1 enum
$statuses = Status::cases();       // ['active', 'pending', 'inactive']
$isValid = Status::isValid('active'); // true
$status = Status::tryFrom('invalid'); // null
```

See [Enum](./enum.md) for complete documentation.

---

## See Also

- [Enum Polyfill Package Overview](../introduction.md) - High-level package documentation
- [Singleton Package](../../singleton/introduction.md) - The Enum trait uses singleton for caching
