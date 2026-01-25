---
id: enum-trait
slug: docs/packages/enum-polyfill/traits/enum
title: Enum Trait
doc_type: reference
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: The Enum trait provides PHP 8.1-style enum methods to classes using constants.
llm_summary: >
  The Enum trait from phpnomad/enum-polyfill gives PHP classes enum-like behavior using
  class constants. It provides methods matching PHP 8.1's native enum API: cases(), from(),
  tryFrom(), getValues(), and isValid(). The trait uses singleton pattern (via WithInstance)
  to cache reflection results for performance. Classes using this trait define constants as
  enum values and get automatic validation, iteration, and safe value retrieval.
questions_answered:
  - How does the Enum trait work?
  - What methods does the Enum trait provide?
  - What is the difference between from() and tryFrom()?
  - How do I validate a value against an enum?
  - How do I get all enum values?
  - Can I add custom methods to enum classes?
audience:
  - developers
  - backend engineers
tags:
  - enum
  - trait
  - polyfill
  - backward-compatibility
llm_tags:
  - enum-trait
  - cases-method
  - from-method
  - tryFrom-method
keywords:
  - Enum trait
  - php enum
  - cases method
  - from method
  - tryFrom method
related:
  - ../introduction
  - ../../singleton/introduction
see_also:
  - ../../auth/introduction
  - ../../http/introduction
noindex: false
---

# Enum Trait

**Namespace:** `PHPNomad\Enum\Traits`

The `Enum` trait adds PHP 8.1-style enum functionality to any class with constants. Add this trait to gain `cases()`, `from()`, `tryFrom()`, and `isValid()` methods.

---

## How It Works

The trait uses reflection to read class constants and provides methods to work with them:

```
┌─────────────────────────────────────────────────────────────┐
│  class Status { use Enum; const Active = 'active'; ... }    │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
            ┌─────────────────────────────────┐
            │   First call to cases()/from()  │
            └─────────────────────────────────┘
                              │
                              ▼
            ┌─────────────────────────────────┐
            │   Reflection reads constants    │
            │   ['Active' => 'active', ...]   │
            └─────────────────────────────────┘
                              │
                              ▼
            ┌─────────────────────────────────┐
            │   Values cached in singleton    │
            │   (via WithInstance trait)      │
            └─────────────────────────────────┘
                              │
                              ▼
            ┌─────────────────────────────────┐
            │   Subsequent calls use cache    │
            └─────────────────────────────────┘
```

The singleton pattern ensures reflection only runs once per class, regardless of how many times you call enum methods.

---

## Methods

### cases()

Returns all enum values as an array.

```php
public static function cases(): array
```

**Returns:** Array of all constant values

**Example:**

```php
class Status
{
    use Enum;

    public const Active = 'active';
    public const Pending = 'pending';
    public const Inactive = 'inactive';
}

$statuses = Status::cases();
// ['active', 'pending', 'inactive']
```

---

### getValues()

Alias for `cases()`. Returns all enum values.

```php
public static function getValues(): array
```

**Returns:** Array of all constant values

---

### isValid()

Checks if a value is a valid enum member.

```php
public static function isValid($value): bool
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `$value` | `mixed` | The value to check |

**Returns:** `true` if value matches a constant, `false` otherwise

**Note:** Uses strict comparison (`===`)

**Example:**

```php
Status::isValid('active');   // true
Status::isValid('invalid');  // false
Status::isValid(null);       // false
```

---

### from()

Returns the value if valid, throws exception if not.

```php
public static function from($value): mixed
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `$value` | `mixed` | The value to validate and return |

**Returns:** The value if it's a valid enum member

**Throws:** `UnexpectedValueException` if value is not valid

**Example:**

```php
$status = Status::from('active');  // 'active'
$status = Status::from('invalid'); // throws UnexpectedValueException
```

**Use for:** Internal code where invalid values indicate bugs

---

### tryFrom()

Returns the value if valid, `null` if not.

```php
public static function tryFrom($value): mixed|null
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `$value` | `mixed` | The value to validate and return |

**Returns:** The value if valid, `null` if not

**Example:**

```php
$status = Status::tryFrom('active');   // 'active'
$status = Status::tryFrom('invalid');  // null
```

**Use for:** User input where invalid values are expected

---

## Basic Usage

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

---

## Real-World Examples

### HTTP Methods (from phpnomad/http)

```php
use PHPNomad\Enum\Traits\Enum;

class Method
{
    use Enum;

    public const Get = 'GET';
    public const Post = 'POST';
    public const Put = 'PUT';
    public const Delete = 'DELETE';
    public const Patch = 'PATCH';
    public const Options = 'OPTIONS';
}

// Usage in a router
function registerRoute(string $method, string $path, callable $handler): void
{
    if (!Method::isValid($method)) {
        throw new InvalidArgumentException("Invalid HTTP method: {$method}");
    }
    // Register route...
}

registerRoute(Method::Get, '/users', $listUsers);
registerRoute(Method::Post, '/users', $createUser);
```

### CRUD Action Types (from phpnomad/auth)

```php
use PHPNomad\Enum\Traits\Enum;

class ActionTypes
{
    use Enum;

    public const Create = 'create';
    public const Read = 'read';
    public const Update = 'update';
    public const Delete = 'delete';
}

// Usage in permission checking
function canPerformAction(User $user, string $action, Resource $resource): bool
{
    $action = ActionTypes::from($action); // Validates the action
    return $user->hasPermission($action, $resource);
}
```

---

## Adding Custom Methods

Unlike native PHP enums (which have limitations), classes using the Enum trait are regular PHP classes—you can add any methods you need:

```php
use PHPNomad\Enum\Traits\Enum;

class Priority
{
    use Enum;

    public const Low = 1;
    public const Medium = 2;
    public const High = 3;
    public const Critical = 4;

    /**
     * Get human-readable label
     */
    public static function getLabel(int $priority): string
    {
        return match ($priority) {
            self::Low => 'Low Priority',
            self::Medium => 'Medium Priority',
            self::High => 'High Priority',
            self::Critical => 'Critical',
            default => 'Unknown',
        };
    }

    /**
     * Check if priority is urgent
     */
    public static function isUrgent(int $priority): bool
    {
        return $priority >= self::High;
    }
}
```

---

## Behavior Notes

| Aspect | Behavior |
|--------|----------|
| Comparison | `isValid()` uses strict type checking (`===`) |
| Caching | Reflection results cached after first access |
| Return values | Returns constant values, not constant names |
| Dependencies | Uses `WithInstance` from singleton package |

---

## Best Practices

### Use from() for internal code, tryFrom() for user input

```php
// Internal code - throw on invalid (indicates bug)
$method = Method::from($routeConfig['method']);

// User input - handle gracefully
$status = Status::tryFrom($userInput);
if ($status === null) {
    // Show error to user
}
```

### Validate at system boundaries

```php
class UserController
{
    public function updateStatus(Request $request): Response
    {
        $status = Status::tryFrom($request->get('status'));

        if ($status === null) {
            return Response::badRequest('Invalid status');
        }

        // Safe to use $status
        $this->userService->setStatus($userId, $status);
    }
}
```

### Use meaningful constant names and values

```php
// Good - clear names, consistent values
class HttpStatus
{
    use Enum;

    public const Ok = 200;
    public const Created = 201;
    public const BadRequest = 400;
    public const NotFound = 404;
}
```

---

## See Also

- [Enum Polyfill Package Overview](../introduction.md) - High-level documentation
- [Singleton Package](../../singleton/introduction.md) - Provides caching mechanism
- [Auth Package](../../auth/introduction.md) - Uses ActionTypes enum
- [HTTP Package](../../http/introduction.md) - Uses Method enum
