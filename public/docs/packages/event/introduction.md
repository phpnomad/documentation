---
id: event-introduction
slug: docs/packages/event/introduction
title: Event Package
doc_type: explanation
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: The event package provides interfaces for implementing event-driven architecture with broadcasting, listening, and handler patterns.
llm_summary: >
  phpnomad/event provides a set of interfaces for implementing event-driven architecture in PHP.
  The package defines Event (identifiable events), EventStrategy (broadcast/attach/detach operations),
  CanHandle (event handlers), HasListeners (objects that provide listener mappings), HasEventBindings
  (objects that provide event bindings), and ActionBindingStrategy (binding actions to events).
  Zero dependencies. Used by auth, rest, update, core, database, wordpress-integration and many other
  packages. Implementations include symfony-event-dispatcher-integration for Symfony EventDispatcher.
questions_answered:
  - What is the event package?
  - How do I implement event-driven architecture in PHPNomad?
  - How do I broadcast events?
  - How do I listen for events?
  - What is an EventStrategy?
  - How do I create event handlers?
  - What packages use the event system?
audience:
  - developers
  - backend engineers
  - architects
tags:
  - events
  - event-driven
  - design-pattern
  - pub-sub
llm_tags:
  - event-pattern
  - publish-subscribe
  - observer-pattern
  - event-broadcasting
keywords:
  - phpnomad event
  - event driven php
  - EventStrategy
  - event broadcasting
  - event listeners
related:
  - ../di/introduction
  - ../database/introduction
  - ../database/caching-and-events
  - ../../core-concepts/bootstrapping/initializers/event-listeners
  - ../../core-concepts/bootstrapping/initializers/event-binding
see_also:
  - interfaces/introduction
  - patterns/best-practices
  - ../symfony-event-dispatcher-integration/introduction
noindex: false
---

# Event

`phpnomad/event` provides **interfaces for event-driven architecture** in PHP applications. Instead of tightly coupling components, the event system lets you:

* **Decouple publishers from subscribers** — Components communicate through events, not direct calls
* **React to state changes** — Listen for events like "record created" or "user logged in"
* **Extend behavior** — Add functionality without modifying existing code
* **Chain actions** — One event can trigger multiple handlers in sequence

---

## Key Concepts

| Concept | Description |
|---------|-------------|
| [Event](interfaces/event) | An object representing something that happened |
| [EventStrategy](interfaces/event-strategy) | The dispatcher that broadcasts events and manages listeners |
| [CanHandle](interfaces/can-handle) | Handler classes that respond to specific events |
| [HasListeners](interfaces/has-listeners) | Declares which events a module listens to |
| [HasEventBindings](interfaces/has-event-bindings) | Flexible binding configuration for platform integration |
| [ActionBindingStrategy](interfaces/action-binding-strategy) | Bridges external systems to internal events |

See [Interfaces Overview](interfaces/introduction) for detailed documentation of each interface.

---

## The Event Lifecycle

```
Something happens in your application
         │
         ▼
┌───────────────────────────┐
│  Create Event object      │
│  implements Event         │
└───────────────────────────┘
         │
         ▼
┌───────────────────────────┐
│  EventStrategy            │
│  broadcast($event)        │
└───────────────────────────┘
         │
         ▼
┌───────────────────────────┐
│  Handlers are called      │
│  in priority order        │
└───────────────────────────┘
         │
    +────┼────+────+
    │    │    │    │
    ▼    ▼    ▼    ▼
  Send  Update Log  Notify
  Email Cache  Event Slack
```

---

## Installation

```bash
composer require phpnomad/event
```

**Requirements:** PHP 7.4+

**Dependencies:** None (zero dependencies)

This package provides **interfaces only**. For a working implementation, install an integration:

```bash
composer require phpnomad/symfony-event-dispatcher-integration
```

---

## Quick Example

### Creating an Event

```php
use PHPNomad\Events\Interfaces\Event;

class UserCreatedEvent implements Event
{
    public function __construct(
        public readonly int $userId,
        public readonly string $email
    ) {}

    public static function getId(): string
    {
        return 'user.created';
    }
}
```

### Creating a Handler

```php
use PHPNomad\Events\Interfaces\CanHandle;
use PHPNomad\Events\Interfaces\Event;

class SendWelcomeEmailHandler implements CanHandle
{
    public function __construct(private EmailService $email) {}

    public function handle(Event $event): void
    {
        $this->email->send($event->email, 'Welcome!');
    }
}
```

### Declaring Listeners

```php
use PHPNomad\Events\Interfaces\HasListeners;

class UserModule implements HasListeners
{
    public function getListeners(): array
    {
        return [
            UserCreatedEvent::class => SendWelcomeEmailHandler::class,
        ];
    }
}
```

### Broadcasting Events

```php
class UserService
{
    public function __construct(private EventStrategy $events) {}

    public function createUser(string $email): User
    {
        $user = new User($email);
        // save user...

        $this->events->broadcast(new UserCreatedEvent(
            userId: $user->getId(),
            email: $user->getEmail()
        ));

        return $user;
    }
}
```

---

## When to Use Events

| Scenario | Why Events Help |
|----------|-----------------|
| Multiple reactions | One action triggers email, logging, cache update |
| Decoupled modules | Modules communicate without direct dependencies |
| Extension points | Add behavior without modifying existing code |
| Audit trails | Log all significant actions centrally |

See [Best Practices](patterns/best-practices) for detailed guidance on when to use events and when not to.

---

## Packages That Use Events

| Package | How It Uses Events |
|---------|-------------------|
| [database](../database/introduction) | Broadcasts RecordCreated, RecordUpdated, RecordDeleted |
| [auth](../auth/introduction) | Authentication lifecycle events |
| [rest](../rest/introduction) | Request/response events via [EventInterceptor](../rest/interceptors/included-interceptors/event-interceptor) |
| [update](../update/introduction) | Update lifecycle events |
| [wordpress-integration](../wordpress-integration/introduction) | Bridges WordPress hooks to application events |

---

## Further Reading

### Package Documentation

* [Interfaces Overview](interfaces/introduction) — All six interfaces explained
* [Best Practices](patterns/best-practices) — Event design, handler patterns, testing strategies

### Related Core Concepts

* [Event Listeners](/core-concepts/bootstrapping/initializers/event-listeners) — Setting up listeners in initializers
* [Event Bindings](/core-concepts/bootstrapping/initializers/event-binding) — Bridging platform events to application events
* [Caching and Events](/packages/database/caching-and-events) — Database CRUD events

### Implementations

* [Symfony Event Dispatcher Integration](../symfony-event-dispatcher-integration/introduction) — Production-ready EventStrategy implementation

---

## Next Steps

1. **Learn the interfaces** → [Interfaces Overview](interfaces/introduction)
2. **See best practices** → [Best Practices](patterns/best-practices)
3. **Get an implementation** → [Symfony Integration](../symfony-event-dispatcher-integration/introduction)
