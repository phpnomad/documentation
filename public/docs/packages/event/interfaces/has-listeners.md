---
id: event-interface-has-listeners
slug: docs/packages/event/interfaces/has-listeners
title: HasListeners Interface
doc_type: reference
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: The HasListeners interface declares which events a module listens to and their handlers.
llm_summary: >
  HasListeners enables declarative event subscription in phpnomad/event. Classes implement
  getListeners() to return an array mapping event class names to handler class names. Supports
  single handlers or arrays of handlers per event. The loader/bootstrapper reads these mappings
  and registers them with EventStrategy. Typically used in initializer classes to declare a
  module's event subscriptions. Handlers are resolved through the DI container when events fire.
questions_answered:
  - What is HasListeners?
  - How do I declare event listeners?
  - Can I have multiple handlers for one event?
  - How does HasListeners work with the DI container?
  - When should I use HasListeners vs attach()?
audience:
  - developers
  - backend engineers
tags:
  - events
  - interface
  - reference
  - configuration
llm_tags:
  - has-listeners
  - event-subscription
  - declarative-config
keywords:
  - HasListeners
  - getListeners
  - event subscription
related:
  - introduction
  - can-handle
  - has-event-bindings
see_also:
  - ../introduction
  - ../../../core-concepts/bootstrapping/initializers/event-listeners
noindex: false
---

# HasListeners Interface

The `HasListeners` interface provides declarative event subscription. Instead of manually calling `attach()`, you declare which events your module listens to and which handlers respond.

---

## Interface Definition

```php
namespace PHPNomad\Events\Interfaces;

interface HasListeners
{
    /**
     * Gets the listeners and their handlers.
     *
     * @return array<class-string<Event>, class-string<CanHandle>[]|class-string<CanHandle>>
     */
    public function getListeners(): array;
}
```

---

## Method Reference

### `getListeners(): array`

Returns a mapping of event classes to handler classes.

| Aspect | Details |
|--------|---------|
| Returns | `array` — Event class names mapped to handler class names |

**Return format:**
```php
[
    EventClass::class => HandlerClass::class,
    // or
    EventClass::class => [HandlerClass1::class, HandlerClass2::class],
]
```

---

## Basic Usage

### Single Handler per Event

```php
use PHPNomad\Events\Interfaces\HasListeners;

class UserModule implements HasListeners
{
    public function getListeners(): array
    {
        return [
            UserCreatedEvent::class => SendWelcomeEmailHandler::class,
            UserDeletedEvent::class => CleanupUserDataHandler::class,
        ];
    }
}
```

### Multiple Handlers per Event

```php
class OrderModule implements HasListeners
{
    public function getListeners(): array
    {
        return [
            OrderPlacedEvent::class => [
                SendOrderConfirmationHandler::class,
                UpdateInventoryHandler::class,
                NotifyWarehouseHandler::class,
            ],
        ];
    }
}
```

### Mixed Single and Multiple

```php
class NotificationModule implements HasListeners
{
    public function getListeners(): array
    {
        return [
            // Single handler
            UserLoggedInEvent::class => UpdateLastLoginHandler::class,

            // Multiple handlers
            PaymentReceivedEvent::class => [
                SendReceiptHandler::class,
                UpdateAccountBalanceHandler::class,
                NotifyAccountingHandler::class,
            ],
        ];
    }
}
```

---

## How It Works

The bootstrapper/loader reads `HasListeners` implementations and registers handlers:

```
┌─────────────────────┐
│   Your Module       │
│ implements          │
│ HasListeners        │
└──────────┬──────────┘
           │ getListeners()
           ▼
┌─────────────────────┐
│   Bootstrapper/     │
│   Loader            │
└──────────┬──────────┘
           │ for each event → handler
           ▼
┌─────────────────────┐
│   EventStrategy     │
│   attach(event,     │
│     container->get( │
│       handler))     │
└─────────────────────┘
```

When an event fires:
1. `EventStrategy` calls the registered listener
2. The listener resolves the handler through the DI container
3. The handler's `handle()` method is called

---

## Where to Use HasListeners

### In Initializers

The most common location is in initializer classes:

```php
class ApplicationInitializer implements HasListeners
{
    public function getListeners(): array
    {
        return [
            UserCreatedEvent::class => [
                CreateUserProfileHandler::class,
                SendWelcomeEmailHandler::class,
            ],
        ];
    }
}
```

### In Service Providers

```php
class PaymentServiceProvider implements HasListeners
{
    public function getListeners(): array
    {
        return [
            PaymentReceivedEvent::class => ProcessPaymentHandler::class,
            PaymentFailedEvent::class => HandlePaymentFailureHandler::class,
            RefundRequestedEvent::class => ProcessRefundHandler::class,
        ];
    }
}
```

### In Module Classes

```php
class BlogModule implements HasListeners
{
    public function getListeners(): array
    {
        return [
            PostPublishedEvent::class => [
                NotifySubscribersHandler::class,
                UpdateSearchIndexHandler::class,
                InvalidateCacheHandler::class,
            ],
        ];
    }
}
```

---

## HasListeners vs Direct attach()

| Approach | When to Use |
|----------|-------------|
| `HasListeners` | Standard case—declaring module's event subscriptions |
| Direct `attach()` | Dynamic listeners, conditional registration, closures |

### HasListeners (Declarative)

```php
// Clean, discoverable, testable
class MyModule implements HasListeners
{
    public function getListeners(): array
    {
        return [
            SomeEvent::class => SomeHandler::class,
        ];
    }
}
```

### Direct attach() (Imperative)

```php
// For dynamic or conditional listeners
$events->attach('some.event', function($event) use ($config) {
    if ($config->isFeatureEnabled('notifications')) {
        // handle
    }
});
```

---

## Best Practices

### 1. Group Related Listeners

Organize listeners by domain:

```php
// Good: cohesive module
class InventoryModule implements HasListeners
{
    public function getListeners(): array
    {
        return [
            OrderPlacedEvent::class => ReserveInventoryHandler::class,
            OrderCancelledEvent::class => ReleaseInventoryHandler::class,
            ShipmentDispatchedEvent::class => DeductInventoryHandler::class,
        ];
    }
}
```

### 2. Use Descriptive Handler Names

Handler names should indicate what they do:

```php
// Good: clear purpose
SendOrderConfirmationEmailHandler::class
UpdateCustomerLoyaltyPointsHandler::class
SyncInventoryWithWarehouseHandler::class

// Bad: vague names
OrderHandler::class
ProcessHandler::class
HandleEvent::class
```

### 3. Order Handlers by Importance

List critical handlers first:

```php
public function getListeners(): array
{
    return [
        PaymentReceivedEvent::class => [
            ValidatePaymentHandler::class,      // Critical: must run first
            UpdateOrderStatusHandler::class,    // Important: business logic
            SendReceiptEmailHandler::class,     // Nice-to-have: notification
            UpdateAnalyticsHandler::class,      // Optional: analytics
        ],
    ];
}
```

---

## Testing

Test that your module declares the expected listeners:

```php
class OrderModuleTest extends TestCase
{
    public function test_declares_order_listeners(): void
    {
        $module = new OrderModule();
        $listeners = $module->getListeners();

        $this->assertArrayHasKey(OrderPlacedEvent::class, $listeners);
        $this->assertContains(
            SendOrderConfirmationHandler::class,
            $listeners[OrderPlacedEvent::class]
        );
    }
}
```

---

## Related Interfaces

- **[CanHandle](can-handle)** — The handlers that are registered
- **[Event](event)** — The events being listened for
- **[HasEventBindings](has-event-bindings)** — Alternative with more flexibility

---

## Further Reading

- **[Event Listeners Guide](/core-concepts/bootstrapping/initializers/event-listeners)** — Tutorial-style guide with examples
- **[Best Practices](../patterns/best-practices)** — Handler patterns and testing strategies
