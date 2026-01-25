---
id: event-interfaces-introduction
slug: docs/packages/event/interfaces/introduction
title: Event Interfaces Overview
doc_type: reference
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: Overview of all interfaces in the event package for implementing event-driven architecture.
llm_summary: >
  The phpnomad/event package provides six interfaces for event-driven architecture: Event (identifiable
  events), EventStrategy (broadcasting and listener management), CanHandle (event handlers), HasListeners
  (declaring event subscriptions), HasEventBindings (flexible binding configuration), and ActionBindingStrategy
  (bridging external systems to internal events). These interfaces enable decoupled, testable architectures
  where components communicate through events rather than direct calls.
questions_answered:
  - What interfaces are in the event package?
  - How do the event interfaces relate to each other?
  - Which interface should I implement for my use case?
audience:
  - developers
  - backend engineers
tags:
  - events
  - interfaces
  - reference
llm_tags:
  - event-interfaces
  - api-reference
keywords:
  - event interfaces
  - EventStrategy
  - CanHandle
  - HasListeners
related:
  - ../introduction
see_also:
  - event
  - event-strategy
  - can-handle
  - has-listeners
  - has-event-bindings
  - action-binding-strategy
noindex: false
---

# Event Interfaces

The `phpnomad/event` package provides six interfaces that work together to enable event-driven architecture. Each interface has a specific role in the event system.

---

## Interface Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                      Event System                               │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────┐     broadcasts     ┌───────────────┐              │
│  │  Event  │ ──────────────────▶│ EventStrategy │              │
│  └─────────┘                    └───────┬───────┘              │
│       ▲                                 │                       │
│       │ implements                      │ calls                 │
│       │                                 ▼                       │
│  ┌─────────────┐               ┌───────────────┐               │
│  │ Your Event  │               │   CanHandle   │               │
│  │   Classes   │               │   (handlers)  │               │
│  └─────────────┘               └───────────────┘               │
│                                        ▲                        │
│                                        │ registered by          │
│                         ┌──────────────┴──────────────┐        │
│                         │                             │         │
│                 ┌───────────────┐          ┌──────────────────┐│
│                 │ HasListeners  │          │ HasEventBindings ││
│                 │ (declarative) │          │ (flexible)       ││
│                 └───────────────┘          └──────────────────┘│
│                                                                 │
│  ┌───────────────────────┐                                     │
│  │ ActionBindingStrategy │ ← Bridges external systems          │
│  └───────────────────────┘                                     │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## Quick Reference

| Interface | Purpose | Key Method |
|-----------|---------|------------|
| [Event](event) | Identify events | `getId(): string` |
| [EventStrategy](event-strategy) | Broadcast and manage listeners | `broadcast()`, `attach()`, `detach()` |
| [CanHandle](can-handle) | Handle events | `handle(Event): void` |
| [HasListeners](has-listeners) | Declare event subscriptions | `getListeners(): array` |
| [HasEventBindings](has-event-bindings) | Flexible binding configuration | `getEventBindings(): array` |
| [ActionBindingStrategy](action-binding-strategy) | Bridge external systems | `bindAction()` |

---

## Choosing the Right Interface

### You want to create an event class
Implement **[Event](event)**. Your class represents something that happened.

```php
class UserCreatedEvent implements Event
{
    public static function getId(): string
    {
        return 'user.created';
    }
}
```

### You want to handle events
Implement **[CanHandle](can-handle)**. Your class responds when specific events occur.

```php
class SendWelcomeEmailHandler implements CanHandle
{
    public function handle(Event $event): void
    {
        // React to the event
    }
}
```

### You want to broadcast events
Inject **[EventStrategy](event-strategy)**. Use it to dispatch events to all listeners.

```php
class UserService
{
    public function __construct(private EventStrategy $events) {}

    public function createUser(): void
    {
        // ... create user
        $this->events->broadcast(new UserCreatedEvent($user));
    }
}
```

### You want to declare what events a module listens to
Implement **[HasListeners](has-listeners)**. Map event classes to handler classes.

```php
class MyModule implements HasListeners
{
    public function getListeners(): array
    {
        return [
            UserCreatedEvent::class => SendWelcomeEmailHandler::class,
        ];
    }
}
```

### You want flexible event binding configuration
Implement **[HasEventBindings](has-event-bindings)**. Provides more configuration options than HasListeners.

### You want to bridge platform events to your application
Use **[ActionBindingStrategy](action-binding-strategy)**. Connects external systems (like WordPress hooks) to your internal events.

---

## Interface Details

- **[Event](event)** — Base interface all events must implement
- **[EventStrategy](event-strategy)** — Core dispatcher for broadcasting and listener management
- **[CanHandle](can-handle)** — Handler interface for responding to events
- **[HasListeners](has-listeners)** — Declarative event-to-handler mapping
- **[HasEventBindings](has-event-bindings)** — Flexible event binding configuration
- **[ActionBindingStrategy](action-binding-strategy)** — Bridge to external event systems
