---
id: event-interface-can-handle
slug: docs/packages/event/interfaces/can-handle
title: CanHandle Interface
doc_type: reference
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: The CanHandle interface defines event handler classes that respond to specific events.
llm_summary: >
  CanHandle is a generic interface for event handler classes in phpnomad/event. Handlers implement
  handle(Event $event): void to respond when their associated event is broadcast. The interface
  uses PHP generics (@template T of Event) for type safety. Handlers are typically registered via
  HasListeners and instantiated through the DI container, enabling dependency injection of services
  like email, logging, or database access. Keep handlers focused on a single responsibility.
questions_answered:
  - What is the CanHandle interface?
  - How do I create an event handler?
  - How do handlers get dependencies?
  - Can one handler handle multiple events?
  - How do I access event data in a handler?
audience:
  - developers
  - backend engineers
tags:
  - events
  - interface
  - reference
  - handlers
llm_tags:
  - can-handle
  - event-handler
  - handler-pattern
keywords:
  - CanHandle
  - event handler
  - handle method
related:
  - introduction
  - event
  - has-listeners
see_also:
  - ../introduction
  - ../patterns/best-practices
noindex: false
---

# CanHandle Interface

The `CanHandle` interface defines event handler classes—dedicated objects that respond when specific events are broadcast.

---

## Interface Definition

```php
namespace PHPNomad\Events\Interfaces;

/**
 * @template T of Event
 */
interface CanHandle
{
    /**
     * Handle the event.
     *
     * @param T $event
     */
    public function handle(Event $event): void;
}
```

---

## Method Reference

### `handle(Event $event): void`

Called when the associated event is broadcast.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$event` | `Event` | The event object containing data about what happened |
| **Returns** | `void` | |

---

## Creating Handlers

### Basic Handler

```php
use PHPNomad\Events\Interfaces\CanHandle;
use PHPNomad\Events\Interfaces\Event;

/**
 * @implements CanHandle<UserCreatedEvent>
 */
class LogUserCreationHandler implements CanHandle
{
    public function handle(Event $event): void
    {
        /** @var UserCreatedEvent $event */
        error_log("User created: {$event->userId}");
    }
}
```

### Handler with Dependencies

Handlers are instantiated through the DI container, enabling dependency injection:

```php
/**
 * @implements CanHandle<UserCreatedEvent>
 */
class SendWelcomeEmailHandler implements CanHandle
{
    public function __construct(
        private EmailStrategy $email,
        private TemplateRenderer $templates
    ) {}

    public function handle(Event $event): void
    {
        /** @var UserCreatedEvent $event */
        $html = $this->templates->render('welcome', [
            'userId' => $event->userId,
            'email' => $event->email,
        ]);

        $this->email->send(
            to: $event->email,
            subject: 'Welcome!',
            body: $html
        );
    }
}
```

### Handler with Error Handling

```php
/**
 * @implements CanHandle<PaymentReceivedEvent>
 */
class ProcessPaymentHandler implements CanHandle
{
    public function __construct(
        private PaymentProcessor $processor,
        private LoggerStrategy $logger
    ) {}

    public function handle(Event $event): void
    {
        /** @var PaymentReceivedEvent $event */
        try {
            $this->processor->confirm($event->transactionId);
        } catch (PaymentException $e) {
            $this->logger->error('Payment confirmation failed', [
                'transaction_id' => $event->transactionId,
                'error' => $e->getMessage(),
            ]);
            // Decide: re-throw, queue for retry, or swallow
        }
    }
}
```

---

## Registering Handlers

Handlers are typically registered via [HasListeners](has-listeners):

```php
class MyModule implements HasListeners
{
    public function getListeners(): array
    {
        return [
            UserCreatedEvent::class => SendWelcomeEmailHandler::class,
            OrderPlacedEvent::class => [
                SendOrderConfirmationHandler::class,
                UpdateInventoryHandler::class,
                NotifyWarehouseHandler::class,
            ],
        ];
    }
}
```

---

## Type Safety with Generics

The `@template` annotation provides IDE support and static analysis:

```php
/**
 * @implements CanHandle<OrderShippedEvent>
 */
class NotifyCustomerOfShipmentHandler implements CanHandle
{
    public function handle(Event $event): void
    {
        // IDE knows $event is OrderShippedEvent
        $trackingNumber = $event->trackingNumber;
        $carrier = $event->carrier;
    }
}
```

**Without the annotation**, you need explicit type assertions:

```php
public function handle(Event $event): void
{
    /** @var OrderShippedEvent $event */
    // or
    assert($event instanceof OrderShippedEvent);
}
```

---

## Best Practices

### 1. One Handler, One Responsibility

Each handler should do one thing:

```php
// Good: focused handlers
class SendWelcomeEmailHandler implements CanHandle { /* sends email */ }
class CreateUserProfileHandler implements CanHandle { /* creates profile */ }
class NotifyAdminOfNewUserHandler implements CanHandle { /* notifies admin */ }

// Bad: handler does too much
class UserCreatedHandler implements CanHandle
{
    public function handle(Event $event): void
    {
        $this->sendWelcomeEmail($event);
        $this->createUserProfile($event);
        $this->notifyAdmin($event);
        $this->updateStatistics($event);
    }
}
```

### 2. Inject Dependencies

Let the container manage dependencies:

```php
// Good: dependencies injected
class NotifySlackHandler implements CanHandle
{
    public function __construct(private SlackClient $slack) {}
}

// Bad: creates own dependencies
class NotifySlackHandler implements CanHandle
{
    public function handle(Event $event): void
    {
        $slack = new SlackClient(getenv('SLACK_TOKEN')); // Hard to test
    }
}
```

### 3. Keep Handlers Fast

Long-running operations should be queued:

```php
// Good: queue heavy work
class GenerateReportHandler implements CanHandle
{
    public function __construct(private Queue $queue) {}

    public function handle(Event $event): void
    {
        $this->queue->push(new GenerateReportJob($event->reportId));
    }
}

// Bad: blocks event dispatch
class GenerateReportHandler implements CanHandle
{
    public function handle(Event $event): void
    {
        $this->generateReport($event->reportId); // Takes 5 minutes!
    }
}
```

### 4. Handle Errors Gracefully

Don't let one handler break others:

```php
public function handle(Event $event): void
{
    try {
        $this->doWork($event);
    } catch (\Exception $e) {
        $this->logger->error('Handler failed', [
            'handler' => self::class,
            'event' => $event->getId(),
            'error' => $e->getMessage(),
        ]);
        // Don't re-throw unless you want to stop other handlers
    }
}
```

---

## Testing Handlers

Handlers are easy to test because they're focused classes with injected dependencies:

```php
class SendWelcomeEmailHandlerTest extends TestCase
{
    public function test_sends_welcome_email(): void
    {
        $email = $this->createMock(EmailStrategy::class);
        $templates = $this->createMock(TemplateRenderer::class);

        $templates->method('render')
            ->with('welcome', ['userId' => 123, 'email' => 'test@example.com'])
            ->willReturn('<html>Welcome!</html>');

        $email->expects($this->once())
            ->method('send')
            ->with(
                to: 'test@example.com',
                subject: 'Welcome!',
                body: '<html>Welcome!</html>'
            );

        $handler = new SendWelcomeEmailHandler($email, $templates);
        $handler->handle(new UserCreatedEvent(
            userId: 123,
            email: 'test@example.com',
            createdAt: new \DateTimeImmutable()
        ));
    }
}
```

---

## Related Interfaces

- **[Event](event)** — Events that handlers receive
- **[EventStrategy](event-strategy)** — Dispatcher that calls handlers
- **[HasListeners](has-listeners)** — Declares which handlers respond to which events

---

## Further Reading

- **[Event Listeners Guide](/core-concepts/bootstrapping/initializers/event-listeners)** — Tutorial-style guide with dependency injection examples
- **[Best Practices](../patterns/best-practices)** — Handler patterns, testing strategies, anti-patterns
- **[Logger Package](../../logger/introduction.md)** — LoggerStrategy for logging in handlers
