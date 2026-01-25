---
id: event-interface-event-strategy
slug: docs/packages/event/interfaces/event-strategy
title: EventStrategy Interface
doc_type: reference
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: The EventStrategy interface defines the core event dispatcher for broadcasting events and managing listeners.
llm_summary: >
  EventStrategy is the central interface for event management in phpnomad/event. It provides three
  methods: broadcast() dispatches an Event to all registered listeners, attach() registers a callable
  to respond to a specific event ID with optional priority, and detach() removes a previously attached
  listener. Implementations include symfony-event-dispatcher-integration. Priority determines execution
  order (higher priority runs first). This is the interface you inject when you need to dispatch events.
questions_answered:
  - What is EventStrategy?
  - How do I broadcast events?
  - How do I attach listeners?
  - How does priority work?
  - How do I detach listeners?
  - What implementations are available?
audience:
  - developers
  - backend engineers
tags:
  - events
  - interface
  - reference
  - dispatcher
llm_tags:
  - event-strategy
  - event-dispatcher
  - broadcast
keywords:
  - EventStrategy
  - broadcast
  - attach
  - detach
  - event dispatcher
related:
  - introduction
  - event
  - can-handle
see_also:
  - ../introduction
  - ../../symfony-event-dispatcher-integration/introduction
noindex: false
---

# EventStrategy Interface

The `EventStrategy` interface is the core dispatcher in PHPNomad's event system. It handles broadcasting events to listeners and managing listener registration.

---

## Interface Definition

```php
namespace PHPNomad\Events\Interfaces;

interface EventStrategy
{
    /**
     * Broadcasts an event to all attached listeners.
     */
    public function broadcast(Event $event): void;

    /**
     * Attaches a listener to an event.
     */
    public function attach(string $event, callable $action, ?int $priority = null): void;

    /**
     * Detaches a listener from an event.
     */
    public function detach(string $event, callable $action, ?int $priority = null): void;
}
```

---

## Method Reference

### `broadcast(Event $event): void`

Dispatches an event to all listeners registered for that event's ID.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$event` | `Event` | The event object to broadcast |
| **Returns** | `void` | |

```php
$events->broadcast(new UserCreatedEvent($user));
```

The event's `getId()` method determines which listeners are called.

---

### `attach(string $event, callable $action, ?int $priority = null): void`

Registers a listener for an event ID.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$event` | `string` | Event ID to listen for |
| `$action` | `callable` | Function to call when event fires |
| `$priority` | `?int` | Execution order (higher = earlier). Default varies by implementation |
| **Returns** | `void` | |

```php
// Attach a closure
$events->attach('user.created', function(UserCreatedEvent $event) {
    // Handle the event
});

// Attach a method
$events->attach('user.created', [$this, 'onUserCreated']);

// Attach with priority
$events->attach('user.created', $handler, priority: 100);
```

---

### `detach(string $event, callable $action, ?int $priority = null): void`

Removes a previously attached listener.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$event` | `string` | Event ID to stop listening for |
| `$action` | `callable` | The exact callable that was attached |
| `$priority` | `?int` | Priority it was attached with |
| **Returns** | `void` | |

```php
// Remove a listener
$events->detach('user.created', $myHandler);
```

**Note:** You must pass the exact same callable reference that was used in `attach()`.

---

## Priority System

Listeners can specify a priority that determines execution order.

```php
// High priority (runs first)
$events->attach('order.placed', $validateHandler, priority: 100);

// Default priority
$events->attach('order.placed', $saveHandler, priority: 50);

// Low priority (runs last)
$events->attach('order.placed', $notifyHandler, priority: 10);
```

**Execution order:** 100 → 50 → 10 (highest to lowest)

### Priority Guidelines

| Priority Range | Use Case |
|----------------|----------|
| 90-100 | Validation, security checks |
| 50-89 | Core business logic |
| 10-49 | Side effects, notifications |
| 1-9 | Logging, cleanup |

---

## Usage Patterns

### Injecting EventStrategy

Use dependency injection to get an `EventStrategy` instance:

```php
class OrderService
{
    public function __construct(
        private EventStrategy $events,
        private OrderRepository $orders
    ) {}

    public function placeOrder(Cart $cart): Order
    {
        $order = Order::fromCart($cart);
        $this->orders->save($order);

        $this->events->broadcast(new OrderPlacedEvent(
            orderId: $order->getId(),
            total: $order->getTotal()
        ));

        return $order;
    }
}
```

### Registering Listeners at Bootstrap

```php
class AppServiceProvider
{
    public function __construct(
        private EventStrategy $events,
        private Container $container
    ) {}

    public function boot(): void
    {
        // Closure listener
        $this->events->attach('user.created', function($event) {
            // Handle event
        });

        // Handler class (resolved through container)
        $this->events->attach('user.created', function($event) {
            $this->container->get(SendWelcomeEmailHandler::class)->handle($event);
        });
    }
}
```

### Conditional Event Handling

```php
$this->events->attach('post.published', function(PostPublishedEvent $event) {
    // Only notify for featured posts
    if ($event->isFeatured) {
        $this->notifier->notifySubscribers($event->postId);
    }
});
```

---

## Implementations

### Symfony EventDispatcher Integration

The primary implementation uses Symfony's EventDispatcher:

```bash
composer require phpnomad/symfony-event-dispatcher-integration
```

See [Symfony Event Dispatcher Integration](/packages/symfony-event-dispatcher-integration/introduction) for details.

### Custom Implementation

You can create your own implementation:

```php
class SimpleEventStrategy implements EventStrategy
{
    private array $listeners = [];

    public function broadcast(Event $event): void
    {
        $id = $event->getId();

        if (!isset($this->listeners[$id])) {
            return;
        }

        // Sort by priority (descending)
        $listeners = $this->listeners[$id];
        usort($listeners, fn($a, $b) => $b['priority'] <=> $a['priority']);

        foreach ($listeners as $listener) {
            ($listener['action'])($event);
        }
    }

    public function attach(string $event, callable $action, ?int $priority = null): void
    {
        $this->listeners[$event][] = [
            'action' => $action,
            'priority' => $priority ?? 0,
        ];
    }

    public function detach(string $event, callable $action, ?int $priority = null): void
    {
        if (!isset($this->listeners[$event])) {
            return;
        }

        $this->listeners[$event] = array_filter(
            $this->listeners[$event],
            fn($l) => $l['action'] !== $action
        );
    }
}
```

---

## Testing with EventStrategy

### Mocking in Tests

```php
class OrderServiceTest extends TestCase
{
    public function test_broadcast_event_on_order_placed(): void
    {
        $events = $this->createMock(EventStrategy::class);

        $events->expects($this->once())
            ->method('broadcast')
            ->with($this->callback(fn($e) =>
                $e instanceof OrderPlacedEvent &&
                $e->orderId === 123
            ));

        $service = new OrderService($events, $this->orders);
        $service->placeOrder($this->cart);
    }
}
```

### Capturing Broadcasted Events

```php
class TestEventStrategy implements EventStrategy
{
    public array $broadcastedEvents = [];

    public function broadcast(Event $event): void
    {
        $this->broadcastedEvents[] = $event;
    }

    // ... attach/detach implementations
}

// In test
$events = new TestEventStrategy();
$service = new OrderService($events, $orders);
$service->placeOrder($cart);

$this->assertCount(1, $events->broadcastedEvents);
$this->assertInstanceOf(OrderPlacedEvent::class, $events->broadcastedEvents[0]);
```

---

## Common Mistakes

### Forgetting to Broadcast

```php
// Bad: event created but never broadcast
public function createUser(): User
{
    $user = new User();
    $this->users->save($user);
    new UserCreatedEvent($user); // Lost!
    return $user;
}

// Good: actually broadcast
public function createUser(): User
{
    $user = new User();
    $this->users->save($user);
    $this->events->broadcast(new UserCreatedEvent($user));
    return $user;
}
```

### Blocking in Listeners

```php
// Bad: slow listener blocks the broadcast
$events->attach('order.placed', function($event) {
    $this->sendEmailToAllSubscribers($event); // Takes 30 seconds!
});

// Good: queue heavy work
$events->attach('order.placed', function($event) {
    $this->queue->push(new SendOrderEmailsJob($event->orderId));
});
```

---

## Related Interfaces

- **[Event](event)** — Events that are broadcast
- **[CanHandle](can-handle)** — Handler classes for events
- **[HasListeners](has-listeners)** — Declarative listener registration
