---
id: event-interface-event
slug: docs/packages/event/interfaces/event
title: Event Interface
doc_type: reference
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: The Event interface defines the contract for all event objects in PHPNomad's event system.
llm_summary: >
  The Event interface is the base contract for all events in phpnomad/event. It requires a single
  static method getId() that returns a unique string identifier for the event type. This ID is used
  by EventStrategy to match events to their listeners. Events should be immutable value objects
  that carry data about something that happened. Common patterns include using class constants or
  fully-qualified class names as IDs.
questions_answered:
  - What is the Event interface?
  - How do I create an event class?
  - What should getId() return?
  - Should events be mutable or immutable?
  - How do I include data in an event?
audience:
  - developers
  - backend engineers
tags:
  - events
  - interface
  - reference
llm_tags:
  - event-interface
  - event-creation
keywords:
  - Event interface
  - getId
  - event class
related:
  - introduction
  - event-strategy
  - can-handle
see_also:
  - ../introduction
  - ../patterns/best-practices
noindex: false
---

# Event Interface

The `Event` interface is the foundation of PHPNomad's event system. Every event class must implement this interface to be broadcastable through `EventStrategy`.

---

## Interface Definition

```php
namespace PHPNomad\Events\Interfaces;

interface Event
{
    /**
     * Returns the unique identifier for this event type.
     *
     * @return string The event identifier
     */
    public static function getId(): string;
}
```

---

## Method Reference

### `getId(): string`

Returns a unique string identifier for the event type.

| Aspect | Details |
|--------|---------|
| Visibility | `public static` |
| Parameters | None |
| Returns | `string` — Unique event identifier |
| Called by | `EventStrategy` when matching events to listeners |

**Important:** This is a static method. The ID identifies the *type* of event, not a specific instance.

---

## Creating Event Classes

### Basic Event

The simplest event just implements the interface:

```php
use PHPNomad\Events\Interfaces\Event;

class UserLoggedInEvent implements Event
{
    public static function getId(): string
    {
        return 'user.logged_in';
    }
}
```

### Event with Data

Events typically carry data about what happened:

```php
use PHPNomad\Events\Interfaces\Event;

class UserCreatedEvent implements Event
{
    public function __construct(
        public readonly int $userId,
        public readonly string $email,
        public readonly \DateTimeImmutable $createdAt
    ) {}

    public static function getId(): string
    {
        return 'user.created';
    }
}
```

### Using Class Name as ID

A common pattern uses the fully-qualified class name:

```php
class OrderPlacedEvent implements Event
{
    public static function getId(): string
    {
        return self::class;
    }
}
```

This guarantees uniqueness but produces longer IDs like `App\Events\OrderPlacedEvent`.

---

## Event ID Patterns

### Dot-notation (Recommended)

```php
public static function getId(): string
{
    return 'user.created';
}
```

Benefits:
- Short, readable
- Natural grouping (`user.*`, `order.*`)
- Easy to type when attaching listeners

### Class Name

```php
public static function getId(): string
{
    return self::class;
}
```

Benefits:
- Guaranteed unique
- Refactoring-safe with IDE support

### Constant

```php
class UserCreatedEvent implements Event
{
    public const ID = 'user.created';

    public static function getId(): string
    {
        return self::ID;
    }
}

// Listeners can reference the constant
$events->attach(UserCreatedEvent::ID, $handler);
```

---

## Best Practices

### 1. Make Events Immutable

Use `readonly` properties (PHP 8.1+) or private properties with getters:

```php
// PHP 8.1+ with readonly
class PaymentReceivedEvent implements Event
{
    public function __construct(
        public readonly string $transactionId,
        public readonly float $amount,
        public readonly \DateTimeImmutable $receivedAt
    ) {}

    public static function getId(): string
    {
        return 'payment.received';
    }
}
```

### 2. Use Past Tense

Events represent things that already happened:

```php
// Good: past tense
class OrderShippedEvent {}
class UserDeletedEvent {}
class PaymentFailedEvent {}

// Bad: present/future tense (sounds like commands)
class ShipOrderEvent {}
class DeleteUserEvent {}
```

### 3. Include Sufficient Context

Events should carry all data handlers need:

```php
// Good: handlers have everything they need
class OrderCompletedEvent implements Event
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $customerId,
        public readonly float $total,
        public readonly array $itemIds,
        public readonly string $shippingMethod
    ) {}
}

// Bad: handlers must query for additional data
class OrderCompletedEvent implements Event
{
    public function __construct(
        public readonly int $orderId
    ) {}
}
```

### 4. Use Value Objects for Complex Data

```php
class ShipmentDispatchedEvent implements Event
{
    public function __construct(
        public readonly int $orderId,
        public readonly Address $shippingAddress,  // Value object
        public readonly Carrier $carrier,          // Value object
        public readonly \DateTimeImmutable $dispatchedAt
    ) {}
}
```

---

## Usage Examples

### Broadcasting an Event

```php
$event = new UserCreatedEvent(
    userId: 123,
    email: 'user@example.com',
    createdAt: new \DateTimeImmutable()
);

$eventStrategy->broadcast($event);
```

### Accessing Event Data in Handlers

```php
class SendWelcomeEmailHandler implements CanHandle
{
    public function handle(Event $event): void
    {
        /** @var UserCreatedEvent $event */
        $this->emailService->send(
            to: $event->email,
            subject: 'Welcome!',
            template: 'welcome',
            data: ['userId' => $event->userId]
        );
    }
}
```

---

## Common Mistakes

### Mutable Events

```php
// Bad: mutable state
class UserUpdatedEvent implements Event
{
    public string $email; // Can be modified by handlers!
}

// Good: immutable
class UserUpdatedEvent implements Event
{
    public function __construct(
        public readonly string $email
    ) {}
}
```

### Missing Data

```php
// Bad: insufficient context
class InvoiceSentEvent implements Event
{
    public function __construct(public readonly int $invoiceId) {}
}

// Handlers need customer email but must query for it
class NotifyCustomerHandler implements CanHandle
{
    public function handle(Event $event): void
    {
        $invoice = $this->invoices->find($event->invoiceId);
        $customer = $this->customers->find($invoice->customerId);
        // Now we can finally send the email
    }
}
```

---

## Related Interfaces

- **[EventStrategy](event-strategy)** — Broadcasts events to listeners
- **[CanHandle](can-handle)** — Handles events when they're broadcast
- **[HasListeners](has-listeners)** — Declares which events a module listens to
