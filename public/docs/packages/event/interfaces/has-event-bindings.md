---
id: event-interface-has-event-bindings
slug: docs/packages/event/interfaces/has-event-bindings
title: HasEventBindings Interface
doc_type: reference
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: The HasEventBindings interface provides flexible event binding configuration for platform integration.
llm_summary: >
  HasEventBindings provides flexible event binding configuration in phpnomad/event. Unlike HasListeners
  which maps events to handlers, HasEventBindings returns an array of binding configurations that can
  include additional metadata like platform actions and transformers. Primary use case is bridging
  platform-specific events (like WordPress hooks) to application events. Each binding specifies an
  event class, an external action to listen for, and a transformer function that converts platform
  data into the application event.
questions_answered:
  - What is HasEventBindings?
  - How is HasEventBindings different from HasListeners?
  - How do I bind platform events to my application?
  - What is an event transformer?
  - When should I use HasEventBindings?
audience:
  - developers
  - backend engineers
tags:
  - events
  - interface
  - reference
  - platform-integration
llm_tags:
  - has-event-bindings
  - platform-bridge
  - event-transformer
keywords:
  - HasEventBindings
  - getEventBindings
  - event transformer
  - platform integration
related:
  - introduction
  - has-listeners
  - action-binding-strategy
see_also:
  - ../introduction
  - ../../../core-concepts/bootstrapping/initializers/event-binding
noindex: false
---

# HasEventBindings Interface

The `HasEventBindings` interface provides flexible event binding configuration, primarily used for bridging platform-specific events to your application's event system.

---

## Interface Definition

```php
namespace PHPNomad\Events\Interfaces;

interface HasEventBindings
{
    /**
     * @return array
     */
    public function getEventBindings(): array;
}
```

---

## Method Reference

### `getEventBindings(): array`

Returns an array of event binding configurations.

| Aspect | Details |
|--------|---------|
| Returns | `array` — Binding configurations |

**Return format:**
```php
[
    EventClass::class => [
        [
            'action' => 'platform_action_name',
            'transformer' => callable,
        ],
    ],
]
```

---

## HasEventBindings vs HasListeners

| Interface | Purpose | Use When |
|-----------|---------|----------|
| `HasListeners` | Map events to handlers | Responding to internal events |
| `HasEventBindings` | Bridge external events | Connecting platform events to your app |

```
HasListeners:
Internal Event → Your Handler

HasEventBindings:
Platform Action → Transformer → Your Event → Your Handlers
```

---

## Basic Usage

### Simple Binding

```php
use PHPNomad\Events\Interfaces\HasEventBindings;

class WordPressIntegration implements HasEventBindings
{
    public function getEventBindings(): array
    {
        return [
            UserCreatedEvent::class => [
                [
                    'action' => 'user_register',
                    'transformer' => function($userId) {
                        $user = get_userdata($userId);
                        return new UserCreatedEvent(
                            userId: $userId,
                            email: $user->user_email,
                            createdAt: new \DateTimeImmutable()
                        );
                    },
                ],
            ],
        ];
    }
}
```

### Multiple Actions for One Event

Different platform actions can trigger the same application event:

```php
public function getEventBindings(): array
{
    return [
        OrderCreatedEvent::class => [
            // WooCommerce new order
            [
                'action' => 'woocommerce_new_order',
                'transformer' => [$this, 'fromWooCommerce'],
            ],
            // Easy Digital Downloads purchase
            [
                'action' => 'edd_complete_purchase',
                'transformer' => [$this, 'fromEdd'],
            ],
        ],
    ];
}

private function fromWooCommerce($orderId): OrderCreatedEvent
{
    $order = wc_get_order($orderId);
    return new OrderCreatedEvent(
        orderId: $orderId,
        customerId: $order->get_customer_id(),
        total: $order->get_total()
    );
}

private function fromEdd($paymentId): OrderCreatedEvent
{
    $payment = new EDD_Payment($paymentId);
    return new OrderCreatedEvent(
        orderId: $paymentId,
        customerId: $payment->customer_id,
        total: $payment->total
    );
}
```

---

## Transformers

Transformers convert platform-specific data into your application events.

### Closure Transformer

```php
'transformer' => function($postId, $post) {
    return new PostPublishedEvent(
        postId: $postId,
        title: $post->post_title,
        authorId: $post->post_author
    );
}
```

### Method Transformer

```php
'transformer' => [$this, 'transformPost']

// ...

private function transformPost($postId, $post): PostPublishedEvent
{
    return new PostPublishedEvent(
        postId: $postId,
        title: $post->post_title,
        authorId: $post->post_author
    );
}
```

### Service Transformer

```php
'transformer' => [$this->postTransformer, 'toEvent']
```

### Conditional Transformation

Return `null` to skip event creation:

```php
'transformer' => function($postId, $post, $update) {
    // Only trigger for new posts, not updates
    if ($update) {
        return null;
    }

    // Only trigger for published posts
    if ($post->post_status !== 'publish') {
        return null;
    }

    return new PostPublishedEvent($postId);
}
```

---

## Real-World Example

### WordPress + WooCommerce Integration

```php
class WooCommerceEventBindings implements HasEventBindings
{
    public function __construct(
        private OrderTransformer $orderTransformer,
        private CustomerTransformer $customerTransformer
    ) {}

    public function getEventBindings(): array
    {
        return [
            // Order lifecycle events
            OrderPlacedEvent::class => [
                [
                    'action' => 'woocommerce_checkout_order_processed',
                    'transformer' => [$this->orderTransformer, 'toOrderPlaced'],
                ],
            ],

            OrderCompletedEvent::class => [
                [
                    'action' => 'woocommerce_order_status_completed',
                    'transformer' => [$this->orderTransformer, 'toOrderCompleted'],
                ],
            ],

            OrderCancelledEvent::class => [
                [
                    'action' => 'woocommerce_order_status_cancelled',
                    'transformer' => [$this->orderTransformer, 'toOrderCancelled'],
                ],
            ],

            // Customer events
            CustomerCreatedEvent::class => [
                [
                    'action' => 'woocommerce_created_customer',
                    'transformer' => [$this->customerTransformer, 'toCustomerCreated'],
                ],
            ],

            // Product events
            ProductPurchasedEvent::class => [
                [
                    'action' => 'woocommerce_order_item_added_to_order',
                    'transformer' => function($itemId, $item, $orderId) {
                        return new ProductPurchasedEvent(
                            productId: $item->get_product_id(),
                            orderId: $orderId,
                            quantity: $item->get_quantity()
                        );
                    },
                ],
            ],
        ];
    }
}
```

---

## Best Practices

### 1. Use Service Classes for Complex Transformations

```php
// Good: dedicated transformer service
class OrderTransformer
{
    public function toOrderPlaced($orderId): OrderPlacedEvent
    {
        $order = wc_get_order($orderId);
        return new OrderPlacedEvent(
            orderId: $orderId,
            customerId: $order->get_customer_id(),
            total: (float) $order->get_total(),
            items: $this->extractItems($order),
            placedAt: new \DateTimeImmutable($order->get_date_created())
        );
    }

    private function extractItems($order): array
    {
        // Complex item extraction logic
    }
}
```

### 2. Handle Missing Data Gracefully

```php
'transformer' => function($postId) {
    $post = get_post($postId);

    if (!$post) {
        return null; // Skip if post doesn't exist
    }

    return new PostPublishedEvent($postId);
}
```

### 3. Document Platform Actions

```php
public function getEventBindings(): array
{
    return [
        ReportCreatedEvent::class => [
            [
                // WordPress 'save_post' action
                // @param int $post_id
                // @param WP_Post $post
                // @param bool $update - true if updating existing post
                'action' => 'save_post',
                'transformer' => function($postId, $post, $update) {
                    if ($update || $post->post_type !== 'report') {
                        return null;
                    }
                    return new ReportCreatedEvent($postId);
                },
            ],
        ],
    ];
}
```

---

## Testing

```php
class WooCommerceEventBindingsTest extends TestCase
{
    public function test_binds_order_completed_event(): void
    {
        $bindings = new WooCommerceEventBindings(
            new OrderTransformer(),
            new CustomerTransformer()
        );

        $eventBindings = $bindings->getEventBindings();

        $this->assertArrayHasKey(OrderCompletedEvent::class, $eventBindings);
        $this->assertEquals(
            'woocommerce_order_status_completed',
            $eventBindings[OrderCompletedEvent::class][0]['action']
        );
    }
}
```

---

## Related Interfaces

- **[HasListeners](has-listeners)** — For internal event handling
- **[ActionBindingStrategy](action-binding-strategy)** — Executes the bindings
- **[Event](event)** — Events created by transformers

---

## Further Reading

- **[Event Bindings Guide](/core-concepts/bootstrapping/initializers/event-binding)** — Tutorial-style guide with WordPress examples
- **[Best Practices](../patterns/best-practices)** — Transformer patterns and testing strategies
