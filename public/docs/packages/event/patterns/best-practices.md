---
id: event-patterns-best-practices
slug: docs/packages/event/patterns/best-practices
title: Event System Best Practices
doc_type: how-to
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: Best practices, common patterns, and testing strategies for PHPNomad's event system.
llm_summary: >
  Comprehensive guide to event-driven architecture best practices in PHPNomad. Covers event design
  (immutability, past tense naming, sufficient context), handler patterns (single responsibility,
  dependency injection, error handling), testing strategies (mocking EventStrategy, testing handlers
  in isolation), and common anti-patterns to avoid. Includes real-world e-commerce example with
  OrderPlacedEvent, PaymentReceivedEvent, and associated handlers demonstrating proper structure.
questions_answered:
  - What are best practices for events?
  - How should I design event classes?
  - How do I test event handlers?
  - What are common event anti-patterns?
  - How do I test that events are broadcast?
  - When should I use events vs direct calls?
audience:
  - developers
  - backend engineers
tags:
  - events
  - best-practices
  - patterns
  - testing
llm_tags:
  - event-best-practices
  - event-testing
  - event-patterns
keywords:
  - event best practices
  - event testing
  - event patterns
  - event anti-patterns
related:
  - ../introduction
  - ../interfaces/introduction
see_also:
  - ../interfaces/event
  - ../interfaces/can-handle
noindex: false
---

# Event System Best Practices

This guide covers best practices for designing events, writing handlers, and testing event-driven code in PHPNomad.

---

## Event Design

### 1. Use Past Tense Names

Events represent things that **have happened**:

```php
// Good: past tense
class UserCreatedEvent {}
class OrderPlacedEvent {}
class PaymentReceivedEvent {}
class ShipmentDispatchedEvent {}

// Bad: present/imperative (sounds like commands)
class CreateUserEvent {}
class PlaceOrderEvent {}
class ReceivePaymentEvent {}
```

### 2. Make Events Immutable

Events are historical records—they shouldn't change after creation:

```php
// Good: immutable with readonly properties (PHP 8.1+)
class OrderPlacedEvent implements Event
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $customerId,
        public readonly float $total,
        public readonly \DateTimeImmutable $placedAt
    ) {}
}

// Bad: mutable properties
class OrderPlacedEvent implements Event
{
    public int $orderId;      // Can be modified!
    public float $total;      // Handlers could change this!
}
```

### 3. Include Sufficient Context

Events should carry all data handlers need—avoid forcing handlers to query for more:

```php
// Good: complete context
class InvoiceSentEvent implements Event
{
    public function __construct(
        public readonly int $invoiceId,
        public readonly int $customerId,
        public readonly string $customerEmail,
        public readonly float $amount,
        public readonly \DateTimeImmutable $sentAt
    ) {}
}

// Bad: insufficient context
class InvoiceSentEvent implements Event
{
    public function __construct(
        public readonly int $invoiceId  // Handlers must query for customer, amount, etc.
    ) {}
}
```

### 4. Use Value Objects for Complex Data

```php
class ShipmentDispatchedEvent implements Event
{
    public function __construct(
        public readonly int $orderId,
        public readonly Address $destination,      // Value object
        public readonly Carrier $carrier,          // Value object
        public readonly TrackingInfo $tracking,    // Value object
        public readonly \DateTimeImmutable $dispatchedAt
    ) {}
}
```

---

## Handler Design

### 1. Single Responsibility

Each handler should do **one thing**:

```php
// Good: focused handlers
class SendWelcomeEmailHandler implements CanHandle { /* sends email */ }
class CreateUserProfileHandler implements CanHandle { /* creates profile */ }
class AddToMailingListHandler implements CanHandle { /* adds to list */ }
class LogUserCreationHandler implements CanHandle { /* logs event */ }

// Bad: handler does too much
class HandleUserCreatedHandler implements CanHandle
{
    public function handle(Event $event): void
    {
        $this->sendWelcomeEmail($event);
        $this->createProfile($event);
        $this->addToMailingList($event);
        $this->logCreation($event);
        $this->notifyAdmin($event);
    }
}
```

### 2. Inject Dependencies

Let the container provide what handlers need:

```php
// Good: dependencies injected
class SendWelcomeEmailHandler implements CanHandle
{
    public function __construct(
        private EmailStrategy $email,
        private TemplateRenderer $templates,
        private LoggerStrategy $logger
    ) {}

    public function handle(Event $event): void
    {
        // Use injected dependencies
    }
}

// Bad: creating dependencies
class SendWelcomeEmailHandler implements CanHandle
{
    public function handle(Event $event): void
    {
        $email = new SmtpEmailService(/* config */);  // Hard to test!
        $email->send(/* ... */);
    }
}
```

### 3. Handle Errors Gracefully

Don't let one handler break others:

```php
class NotifySlackHandler implements CanHandle
{
    public function __construct(
        private SlackClient $slack,
        private LoggerStrategy $logger
    ) {}

    public function handle(Event $event): void
    {
        try {
            $this->slack->notify($this->formatMessage($event));
        } catch (SlackException $e) {
            // Log but don't re-throw—let other handlers run
            $this->logger->warning('Slack notification failed', [
                'event' => $event->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
```

### 4. Keep Handlers Fast

Queue long-running operations:

```php
// Good: queue heavy work
class GenerateInvoicePdfHandler implements CanHandle
{
    public function __construct(private Queue $queue) {}

    public function handle(Event $event): void
    {
        $this->queue->push(new GenerateInvoicePdfJob(
            $event->orderId
        ));
    }
}

// Bad: blocks event dispatch
class GenerateInvoicePdfHandler implements CanHandle
{
    public function handle(Event $event): void
    {
        $pdf = $this->generatePdf($event->orderId);  // Takes 30 seconds!
        $this->saveToDisk($pdf);
        $this->uploadToS3($pdf);
    }
}
```

---

## When to Use Events

Events are appropriate when:

| Scenario | Why Events |
|----------|------------|
| Multiple reactions | One action triggers email, logging, analytics |
| Decoupled modules | Modules communicate without direct dependencies |
| Extension points | Allow adding behavior without modifying code |
| Audit/compliance | Log all significant actions |
| Eventual consistency | Components update asynchronously |

### Good Use Cases

```php
// User lifecycle
$events->broadcast(new UserRegisteredEvent($user));
$events->broadcast(new UserVerifiedEvent($user));
$events->broadcast(new UserDeletedEvent($userId));

// Business events
$events->broadcast(new OrderPlacedEvent($order));
$events->broadcast(new PaymentReceivedEvent($payment));
$events->broadcast(new ShipmentDispatchedEvent($shipment));
```

---

## When NOT to Use Events

### Need a Return Value

```php
// Bad: events don't return values
$event = new ValidateOrderEvent($order);
$events->broadcast($event);
$isValid = $event->isValid;  // Awkward!

// Good: direct call
$isValid = $this->validator->validate($order);
```

### Simple 1:1 Relationships

```php
// Overkill: event for simple call
$events->broadcast(new CalculateTaxEvent($price));

// Just call the service
$tax = $this->taxCalculator->calculate($price);
```

### Performance-Critical Code

```php
// Bad: event overhead in tight loop
foreach ($items as $item) {
    $events->broadcast(new ItemProcessedEvent($item));
}

// Better: batch event
$events->broadcast(new ItemsProcessedEvent($items));

// Or: no event if just internal processing
foreach ($items as $item) {
    $this->process($item);
}
```

---

## Testing Strategies

### Testing Event Broadcasting

Verify that services broadcast the right events:

```php
class OrderServiceTest extends TestCase
{
    public function test_broadcasts_order_placed_event(): void
    {
        $events = $this->createMock(EventStrategy::class);

        $events->expects($this->once())
            ->method('broadcast')
            ->with($this->callback(function(Event $event) {
                return $event instanceof OrderPlacedEvent
                    && $event->customerId === 123
                    && $event->total === 99.99;
            }));

        $service = new OrderService($events, $this->orders);
        $service->placeOrder($this->cart, $this->customer);
    }
}
```

### Testing Handlers in Isolation

```php
class SendOrderConfirmationHandlerTest extends TestCase
{
    public function test_sends_confirmation_email(): void
    {
        $email = $this->createMock(EmailStrategy::class);

        $email->expects($this->once())
            ->method('send')
            ->with(
                'customer@example.com',
                'Order Confirmation',
                $this->stringContains('Order #456')
            );

        $handler = new SendOrderConfirmationHandler($email);
        $handler->handle(new OrderPlacedEvent(
            orderId: 456,
            customerId: 123,
            customerEmail: 'customer@example.com',
            total: 99.99,
            placedAt: new \DateTimeImmutable()
        ));
    }
}
```

### Capturing Events for Assertions

```php
class SpyEventStrategy implements EventStrategy
{
    public array $events = [];

    public function broadcast(Event $event): void
    {
        $this->events[] = $event;
    }

    public function attach(string $event, callable $action, ?int $priority = null): void {}
    public function detach(string $event, callable $action, ?int $priority = null): void {}
}

// In test
public function test_order_flow_broadcasts_expected_events(): void
{
    $events = new SpyEventStrategy();
    $service = new OrderService($events, $this->orders, $this->payments);

    $service->placeOrder($this->cart);
    $service->processPayment($this->order, $this->paymentMethod);

    $this->assertCount(2, $events->events);
    $this->assertInstanceOf(OrderPlacedEvent::class, $events->events[0]);
    $this->assertInstanceOf(PaymentReceivedEvent::class, $events->events[1]);
}
```

### Testing HasListeners Declarations

```php
class OrderModuleTest extends TestCase
{
    public function test_registers_expected_listeners(): void
    {
        $module = new OrderModule();
        $listeners = $module->getListeners();

        $this->assertArrayHasKey(OrderPlacedEvent::class, $listeners);
        $this->assertContains(
            SendOrderConfirmationHandler::class,
            (array) $listeners[OrderPlacedEvent::class]
        );
    }
}
```

---

## Common Anti-Patterns

### Mutable Events

```php
// Anti-pattern: event modified by handlers
class UserUpdatedEvent implements Event
{
    public array $changes = [];
}

// Handler 1 adds to changes
// Handler 2 reads changes but sees Handler 1's modifications
// Order-dependent, hard to debug
```

### Event Chains

```php
// Anti-pattern: handler broadcasts another event
class UpdateInventoryHandler implements CanHandle
{
    public function handle(Event $event): void
    {
        $this->inventory->reduce($event->items);
        $this->events->broadcast(new InventoryUpdatedEvent());  // Cascades!
    }
}

// Can lead to infinite loops or hard-to-trace flows
```

### Using Events for Control Flow

```php
// Anti-pattern: using events to control execution
class ProcessOrderHandler implements CanHandle
{
    public function handle(Event $event): void
    {
        if (!$event->validated) {  // Checking state set by another handler
            return;
        }
        // process...
    }
}
```

### Over-Eventing

```php
// Anti-pattern: event for every tiny thing
$events->broadcast(new DatabaseQueryExecutedEvent());
$events->broadcast(new CacheHitEvent());
$events->broadcast(new LogMessageWrittenEvent());

// Creates noise, performance overhead
```

---

## Real-World Example

### E-commerce Order System

```php
// Events
class OrderPlacedEvent implements Event
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $customerId,
        public readonly string $customerEmail,
        public readonly float $total,
        public readonly array $items,
        public readonly \DateTimeImmutable $placedAt
    ) {}

    public static function getId(): string { return 'order.placed'; }
}

class PaymentReceivedEvent implements Event
{
    public function __construct(
        public readonly int $orderId,
        public readonly string $transactionId,
        public readonly float $amount,
        public readonly \DateTimeImmutable $receivedAt
    ) {}

    public static function getId(): string { return 'payment.received'; }
}

// Handlers
class SendOrderConfirmationHandler implements CanHandle
{
    public function __construct(private EmailStrategy $email) {}

    public function handle(Event $event): void
    {
        $this->email->send(
            $event->customerEmail,
            'Order Confirmation',
            $this->formatEmail($event)
        );
    }
}

class ReserveInventoryHandler implements CanHandle
{
    public function __construct(private InventoryService $inventory) {}

    public function handle(Event $event): void
    {
        foreach ($event->items as $item) {
            $this->inventory->reserve($item['sku'], $item['quantity']);
        }
    }
}

class NotifyWarehouseHandler implements CanHandle
{
    public function __construct(private WarehouseClient $warehouse) {}

    public function handle(Event $event): void
    {
        $this->warehouse->queueForFulfillment($event->orderId);
    }
}

// Module registration
class OrderModule implements HasListeners
{
    public function getListeners(): array
    {
        return [
            OrderPlacedEvent::class => [
                SendOrderConfirmationHandler::class,
                ReserveInventoryHandler::class,
            ],
            PaymentReceivedEvent::class => [
                NotifyWarehouseHandler::class,
                UpdateCustomerLoyaltyHandler::class,
            ],
        ];
    }
}

// Service usage
class OrderService
{
    public function __construct(
        private EventStrategy $events,
        private OrderRepository $orders
    ) {}

    public function placeOrder(Cart $cart, Customer $customer): Order
    {
        $order = Order::fromCart($cart, $customer);
        $this->orders->save($order);

        $this->events->broadcast(new OrderPlacedEvent(
            orderId: $order->getId(),
            customerId: $customer->getId(),
            customerEmail: $customer->getEmail(),
            total: $order->getTotal(),
            items: $order->getItems(),
            placedAt: new \DateTimeImmutable()
        ));

        return $order;
    }
}
```

---

## Related Documentation

- [Logger Package](../../logger/introduction.md) - LoggerStrategy used in handlers for logging
- [Event Interfaces](../interfaces/introduction.md) - Core event interfaces
- [Event Listeners Guide](/core-concepts/bootstrapping/initializers/event-listeners.md) - Event listener registration
