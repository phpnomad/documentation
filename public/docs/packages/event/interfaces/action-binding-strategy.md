---
id: event-interface-action-binding-strategy
slug: docs/packages/event/interfaces/action-binding-strategy
title: ActionBindingStrategy Interface
doc_type: reference
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: The ActionBindingStrategy interface binds external platform actions to internal application events.
llm_summary: >
  ActionBindingStrategy bridges external platform actions (like WordPress hooks) to internal events
  in phpnomad/event. The bindAction() method registers a listener on an external action that creates
  and broadcasts an internal event when triggered. Accepts an event class, the external action name,
  and an optional transformer to convert platform data into event constructor arguments. Used by
  wordpress-integration to connect WordPress actions to PHPNomad events without coupling application
  code to WordPress.
questions_answered:
  - What is ActionBindingStrategy?
  - How do I connect WordPress hooks to my events?
  - What is the transformer parameter for?
  - How does ActionBindingStrategy work with EventStrategy?
audience:
  - developers
  - backend engineers
tags:
  - events
  - interface
  - reference
  - platform-integration
llm_tags:
  - action-binding-strategy
  - platform-bridge
  - wordpress-hooks
keywords:
  - ActionBindingStrategy
  - bindAction
  - platform hooks
  - WordPress integration
related:
  - introduction
  - has-event-bindings
  - event-strategy
see_also:
  - ../introduction
  - ../../wordpress-integration/introduction
noindex: false
---

# ActionBindingStrategy Interface

The `ActionBindingStrategy` interface connects external platform actions (like WordPress hooks) to your internal event system.

---

## Interface Definition

```php
namespace PHPNomad\Events\Interfaces;

interface ActionBindingStrategy
{
    /**
     * Binds an external action to an event class.
     *
     * @param string $eventClass The event class to create
     * @param string $actionToBind The external action to listen for
     * @param callable|null $transformer Converts action args to event constructor args
     */
    public function bindAction(string $eventClass, string $actionToBind, ?callable $transformer = null);
}
```

---

## Method Reference

### `bindAction(string $eventClass, string $actionToBind, ?callable $transformer = null)`

Registers a binding between an external action and an internal event.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$eventClass` | `string` | Fully-qualified class name of the Event to create |
| `$actionToBind` | `string` | Name of the external action/hook to listen for |
| `$transformer` | `?callable` | Converts action arguments to event constructor arguments |

**Flow:**
```
External Action fires
        │
        ▼
ActionBindingStrategy intercepts
        │
        ▼
Transformer converts arguments
        │
        ▼
Event instance created
        │
        ▼
EventStrategy broadcasts
        │
        ▼
Your handlers respond
```

---

## Basic Usage

### Without Transformer

When the external action provides arguments matching your event constructor:

```php
// Event expects ($userId)
// WordPress 'user_register' hook provides ($userId)
$binding->bindAction(
    UserCreatedEvent::class,
    'user_register'
);
```

### With Transformer

When arguments need conversion:

```php
$binding->bindAction(
    OrderPlacedEvent::class,
    'woocommerce_new_order',
    function($orderId) {
        $order = wc_get_order($orderId);
        return [
            $orderId,
            $order->get_customer_id(),
            (float) $order->get_total(),
        ];
    }
);
```

The transformer returns an array of arguments for the event constructor.

---

## How It Works

### Conceptual Implementation

```php
class WordPressActionBindingStrategy implements ActionBindingStrategy
{
    public function __construct(private EventStrategy $events) {}

    public function bindAction(
        string $eventClass,
        string $actionToBind,
        ?callable $transformer = null
    ) {
        add_action($actionToBind, function(...$args) use ($eventClass, $transformer) {
            // Transform arguments if transformer provided
            $eventArgs = $transformer ? $transformer(...$args) : $args;

            // Create event instance
            $event = new $eventClass(...$eventArgs);

            // Broadcast through internal event system
            $this->events->broadcast($event);
        });
    }
}
```

---

## Usage Patterns

### Binding at Bootstrap

```php
class WordPressBootstrapper
{
    public function __construct(
        private ActionBindingStrategy $bindings
    ) {}

    public function boot(): void
    {
        // User events
        $this->bindings->bindAction(
            UserCreatedEvent::class,
            'user_register',
            fn($userId) => [$userId, get_userdata($userId)->user_email]
        );

        // Post events
        $this->bindings->bindAction(
            PostPublishedEvent::class,
            'publish_post',
            fn($postId, $post) => [$postId, $post->post_title]
        );
    }
}
```

### Processing HasEventBindings

`ActionBindingStrategy` is often used to process `HasEventBindings` declarations:

```php
class EventBindingProcessor
{
    public function __construct(
        private ActionBindingStrategy $bindings
    ) {}

    public function process(HasEventBindings $provider): void
    {
        foreach ($provider->getEventBindings() as $eventClass => $configs) {
            foreach ($configs as $config) {
                $this->bindings->bindAction(
                    $eventClass,
                    $config['action'],
                    $config['transformer'] ?? null
                );
            }
        }
    }
}
```

---

## Transformer Patterns

### Identity Transformer (Pass-through)

When action args match event constructor:

```php
// No transformer needed
$binding->bindAction(SimpleEvent::class, 'simple_action');

// Explicit pass-through
$binding->bindAction(
    SimpleEvent::class,
    'simple_action',
    fn(...$args) => $args
);
```

### Extracting Data

```php
$binding->bindAction(
    CustomerCreatedEvent::class,
    'woocommerce_created_customer',
    function($customerId, $newCustomerData, $passwordGenerated) {
        // Only pass what the event needs
        return [
            $customerId,
            $newCustomerData['user_email'],
        ];
    }
);
```

### Enriching Data

```php
$binding->bindAction(
    PostPublishedEvent::class,
    'publish_post',
    function($postId, $post) {
        $author = get_userdata($post->post_author);
        return [
            $postId,
            $post->post_title,
            $author->user_email,
            new \DateTimeImmutable($post->post_date),
        ];
    }
);
```

### Conditional Binding

Return `null` from transformer to skip event creation:

```php
$binding->bindAction(
    ProductPublishedEvent::class,
    'save_post',
    function($postId, $post, $update) {
        // Only for new products, not updates
        if ($update || $post->post_type !== 'product') {
            return null;
        }
        return [$postId, $post->post_title];
    }
);
```

---

## Real-World Example

### Complete WordPress Integration

```php
class WordPressEventBridge
{
    public function __construct(
        private ActionBindingStrategy $bindings
    ) {}

    public function register(): void
    {
        $this->registerUserBindings();
        $this->registerPostBindings();
        $this->registerCommentBindings();
    }

    private function registerUserBindings(): void
    {
        // User registration
        $this->bindings->bindAction(
            UserRegisteredEvent::class,
            'user_register',
            function($userId) {
                $user = get_userdata($userId);
                return [
                    $userId,
                    $user->user_email,
                    $user->user_login,
                ];
            }
        );

        // Profile update
        $this->bindings->bindAction(
            UserProfileUpdatedEvent::class,
            'profile_update',
            fn($userId, $oldData) => [$userId, $oldData]
        );

        // User deletion
        $this->bindings->bindAction(
            UserDeletedEvent::class,
            'delete_user',
            fn($userId, $reassignId) => [$userId]
        );
    }

    private function registerPostBindings(): void
    {
        // New post published
        $this->bindings->bindAction(
            PostPublishedEvent::class,
            'publish_post',
            fn($postId, $post) => [$postId, $post->post_title, $post->post_author]
        );

        // Post status changed
        $this->bindings->bindAction(
            PostStatusChangedEvent::class,
            'transition_post_status',
            fn($new, $old, $post) => [$post->ID, $old, $new]
        );
    }

    private function registerCommentBindings(): void
    {
        $this->bindings->bindAction(
            CommentPostedEvent::class,
            'wp_insert_comment',
            function($commentId, $comment) {
                return [
                    $commentId,
                    $comment->comment_post_ID,
                    $comment->comment_author_email,
                ];
            }
        );
    }
}
```

---

## Testing

Mock `ActionBindingStrategy` to verify bindings are registered:

```php
class WordPressEventBridgeTest extends TestCase
{
    public function test_registers_user_bindings(): void
    {
        $bindings = $this->createMock(ActionBindingStrategy::class);

        $bindings->expects($this->atLeastOnce())
            ->method('bindAction')
            ->with(
                $this->equalTo(UserRegisteredEvent::class),
                $this->equalTo('user_register'),
                $this->isType('callable')
            );

        $bridge = new WordPressEventBridge($bindings);
        $bridge->register();
    }
}
```

---

## Related Interfaces

- **[HasEventBindings](has-event-bindings)** — Declarative binding configuration
- **[EventStrategy](event-strategy)** — Receives broadcasted events
- **[Event](event)** — Events created from bindings

---

## Further Reading

- **[Event Bindings Guide](/core-concepts/bootstrapping/initializers/event-binding)** — Tutorial-style guide with WordPress examples
- **[Best Practices](../patterns/best-practices)** — Transformer patterns and testing strategies
