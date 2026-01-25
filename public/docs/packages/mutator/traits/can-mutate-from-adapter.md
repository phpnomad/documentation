---
id: mutator-trait-can-mutate-from-adapter
slug: docs/packages/mutator/traits/can-mutate-from-adapter
title: CanMutateFromAdapter Trait
doc_type: reference
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: The CanMutateFromAdapter trait automates the adapter-based mutation workflow with a single mutate() method.
llm_summary: >
  The CanMutateFromAdapter trait provides a mutate(...$args) method that implements the full
  adapter workflow: convertFromSource() creates a Mutator, mutate() transforms it, and
  convertToResult() extracts the output. Classes using this trait must have a $mutationAdapter
  property of type MutationAdapter. This eliminates boilerplate when using the adapter pattern.
questions_answered:
  - What does CanMutateFromAdapter do?
  - How do I use the CanMutateFromAdapter trait?
  - What are the requirements for using this trait?
  - How does the trait implement the adapter workflow?
audience:
  - developers
  - backend engineers
tags:
  - trait
  - mutator
  - adapter
llm_tags:
  - can-mutate-from-adapter
  - adapter-workflow
keywords:
  - CanMutateFromAdapter trait
  - adapter workflow
  - mutation trait
related:
  - ../introduction
  - ../interfaces/mutation-adapter
see_also:
  - ../interfaces/mutator
  - ../interfaces/mutator-handler
noindex: false
---

# CanMutateFromAdapter Trait

The `CanMutateFromAdapter` trait implements the **adapter-based mutation workflow** automatically. Instead of manually calling `convertFromSource()`, `mutate()`, and `convertToResult()`, the trait provides a single `mutate()` method that handles everything.

## Trait definition

```php
namespace PHPNomad\Mutator\Traits;

use PHPNomad\Mutator\Interfaces\MutationAdapter;

trait CanMutateFromAdapter
{
    protected MutationAdapter $mutationAdapter;

    public function mutate(...$args)
    {
        $mutation = $this->mutationAdapter->convertFromSource(...$args);
        $mutation->mutate();
        return $this->mutationAdapter->convertToResult($mutation);
    }
}
```

## Requirements

To use this trait, your class must:

1. Have a `$mutationAdapter` property of type `MutationAdapter`
2. Initialize the adapter (typically in the constructor)

---

## The workflow

The trait automates this three-step process:

```
mutate($args)
    │
    ▼
┌───────────────────────────────────┐
│ 1. convertFromSource(...$args)    │
│    → Creates a Mutator instance   │
└───────────────────────────────────┘
    │
    ▼
┌───────────────────────────────────┐
│ 2. $mutator->mutate()             │
│    → Transforms internal state    │
└───────────────────────────────────┘
    │
    ▼
┌───────────────────────────────────┐
│ 3. convertToResult($mutator)      │
│    → Extracts and returns output  │
└───────────────────────────────────┘
    │
    ▼
return result
```

---

## Basic usage

```php
use PHPNomad\Mutator\Traits\CanMutateFromAdapter;
use PHPNomad\Mutator\Interfaces\MutationAdapter;

class SlugService
{
    use CanMutateFromAdapter;

    protected MutationAdapter $mutationAdapter;

    public function __construct()
    {
        $this->mutationAdapter = new SlugAdapter();
    }
}

// The trait provides mutate()
$service = new SlugService();
echo $service->mutate('Hello World!'); // "hello-world-"
```

---

## With dependency injection

```php
class ContactFormService
{
    use CanMutateFromAdapter;

    protected MutationAdapter $mutationAdapter;

    public function __construct(ContactFormAdapter $adapter)
    {
        $this->mutationAdapter = $adapter;
    }
}

// In your DI container registration
$container->set(ContactFormService::class, function($c) {
    return new ContactFormService(
        $c->get(ContactFormAdapter::class)
    );
});

// Usage
$service = $container->get(ContactFormService::class);
$result = $service->mutate([
    'email' => 'user@example.com',
    'name' => 'John Doe',
    'message' => 'Hello!'
]);
```

---

## Multiple adapters

You can create services with different adapters for different use cases:

```php
class UserService
{
    use CanMutateFromAdapter;

    protected MutationAdapter $mutationAdapter;

    public function __construct(MutationAdapter $adapter)
    {
        $this->mutationAdapter = $adapter;
    }
}

// Different adapters for different contexts
$registrationService = new UserService(new RegistrationAdapter());
$profileService = new UserService(new ProfileUpdateAdapter());

// Same interface, different behavior
$newUser = $registrationService->mutate($registrationData);
$updatedProfile = $profileService->mutate($profileData);
```

---

## Extending the trait

You can add methods that use the trait's `mutate()`:

```php
class FormService
{
    use CanMutateFromAdapter;

    protected MutationAdapter $mutationAdapter;

    public function __construct()
    {
        $this->mutationAdapter = new FormAdapter();
    }

    // Add convenience methods
    public function validateAndSubmit(array $data): array
    {
        $result = $this->mutate($data);

        if ($result['success']) {
            $this->sendNotification($result['data']);
        }

        return $result;
    }

    private function sendNotification(array $data): void
    {
        // Send email, log, etc.
    }
}
```

---

## Without the trait

For comparison, here's what you'd write without the trait:

```php
// Without trait - manual workflow
class SlugService
{
    private MutationAdapter $adapter;

    public function __construct()
    {
        $this->adapter = new SlugAdapter();
    }

    public function mutate(...$args)
    {
        // Must write this yourself
        $mutator = $this->adapter->convertFromSource(...$args);
        $mutator->mutate();
        return $this->adapter->convertToResult($mutator);
    }
}
```

The trait eliminates this boilerplate.

---

## Best practices

### Initialize the adapter in the constructor

```php
// Good: adapter initialized in constructor
public function __construct(MyAdapter $adapter)
{
    $this->mutationAdapter = $adapter;
}

// Bad: adapter created lazily (may cause null errors)
public function mutate(...$args)
{
    $this->mutationAdapter ??= new MyAdapter();  // Risky
    // ...
}
```

### Use interfaces for adapter type hints

```php
// Good: accepts any MutationAdapter
public function __construct(MutationAdapter $adapter)
{
    $this->mutationAdapter = $adapter;
}

// Less flexible: locked to specific adapter
public function __construct(SlugAdapter $adapter)
{
    $this->mutationAdapter = $adapter;
}
```

### Don't override mutate() unless necessary

The trait's `mutate()` handles the standard workflow. Override only if you need custom behavior:

```php
// Override only when needed
public function mutate(...$args)
{
    $this->logger->info('Starting mutation', ['args' => $args]);

    $result = parent::mutate(...$args);  // Call trait method

    $this->logger->info('Mutation complete', ['result' => $result]);

    return $result;
}
```

---

## Testing

Test the service with mock adapters:

```php
class SlugServiceTest extends TestCase
{
    public function test_delegates_to_adapter(): void
    {
        $mockAdapter = $this->createMock(MutationAdapter::class);
        $mockMutator = $this->createMock(Mutator::class);

        $mockAdapter->expects($this->once())
            ->method('convertFromSource')
            ->with('input')
            ->willReturn($mockMutator);

        $mockMutator->expects($this->once())
            ->method('mutate');

        $mockAdapter->expects($this->once())
            ->method('convertToResult')
            ->with($mockMutator)
            ->willReturn('output');

        $service = new class($mockAdapter) {
            use CanMutateFromAdapter;

            protected MutationAdapter $mutationAdapter;

            public function __construct(MutationAdapter $adapter)
            {
                $this->mutationAdapter = $adapter;
            }
        };

        $result = $service->mutate('input');

        $this->assertEquals('output', $result);
    }
}
```

---

## See also

- [MutationAdapter](../interfaces/mutation-adapter) — The adapter interface this trait works with
- [Mutator](../interfaces/mutator) — The mutator interface adapters create
- [Package Introduction](../introduction) — Overview of the mutator package
