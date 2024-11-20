# Event Bindings

## What are Event Bindings?

Event bindings are like adapters that connect your application's events to a platform's native event system. Think of
them as translators that help your code communicate with different platforms (like WordPress, Laravel, or your own
custom system) without needing to know their specific "language".

## Why Use Event Bindings?

The beauty of event bindings is that they keep your core application code clean and portable. Instead of embedding
platform-specific code throughout your application, you create a single place where your system's events connect to the
platform.

For example, let's say you have an e-commerce application and want to track when someone uses a coupon code. Rather than
spreading WordPress-specific code everywhere, you can use event bindings to cleanly connect WordPress's coupon events to
your system.

## How Event Bindings Work

Event bindings consist of three main parts:

1. Your application's event (like `CouponApplied` or `ReportCreated`)
2. The platform's action or event to listen for
3. A transformer that converts the platform's data into your application's event

Here's a basic example:

```php
class WooCommerceIntegration implements HasEventBindings 
{
    public function getEventBindings(): array 
    {
        return [
            // Your event => Platform event configuration
            CouponApplied::class => [
                [
                    'action' => 'woocommerce_applied_coupon',
                    'transformer' => function($couponCode) {
                        return new CouponApplied($couponCode);
                    }
                ]
            ]
        ];
    }
}
```

In this example:

- `CouponApplied` is your application's event
- `woocommerce_applied_coupon` is WooCommerce's action
- The transformer function converts WooCommerce's coupon code into your `CouponApplied` event

## Advanced Usage: Multiple Bindings

Sometimes you might want to listen for the same event from different sources. You can do this by returning an array of
bindings:

```php
public function getEventBindings(): array 
{
    return [
        OrderCreated::class => [
            // Listen for WooCommerce orders
            [
                'action' => 'woocommerce_order_status_completed',
                'transformer' => [$this->wooCommerceTransformer, 'toOrderCreated']
            ],
            // Also listen for Easy Digital Downloads orders
            [
                'action' => 'edd_complete_purchase',
                'transformer' => [$this->eddTransformer, 'toOrderCreated']
            ]
        ]
    ];
}
```

## Working with Transformers

Transformers are functions that:

1. Receive data from the platform's event
2. Convert that data into your application's format
3. Return either your event object or null

If a transformer returns null, no event is triggered. This is useful for conditional events:

```php
'transformer' => function($post_id, $post) {
    // Only create event for published posts
    if ($post->post_status !== 'publish') {
        return null;
    }
    
    return new PostPublished($post_id);
}
```

## Best Practices

1. **Keep Transformers Clean**: Transformers should focus only on converting data. Heavy processing should happen
   elsewhere.

2. **Use Service Classes**: For complex transformations, create dedicated service classes instead of inline functions:

```php
'transformer' => [$this->postTransformerService, 'toPostEvent']
```

3. **Handle Errors Gracefully**: Transformers should handle missing or invalid data without crashing:

```php
'transformer' => function($data) {
    try {
        return new MyEvent($data);
    } catch (Exception $e) {
        // Log error, return null to skip event
        return null;
    }
}
```

4. **Document Platform Events**: Always document which platform events you're binding to and what data they provide:

```php
// Binds to 'save_post' WordPress action
// @param int $post_id The ID of the saved post
// @param WP_Post $post The post object
// @param bool $update Whether this is an update
```

## Real-World Example: Reports System

Here's a complete example showing how to bind a reports system to WordPress:

```php
class WordPressReportingIntegration implements HasEventBindings 
{
    private ReportTransformer $transformer;
    
    public function __construct(ReportTransformer $transformer) 
    {
        $this->transformer = $transformer;
    }
    
    public function getEventBindings(): array 
    {
        return [
            // Handle new reports
            ReportCreated::class => [
                [
                    'action' => 'save_post',
                    'transformer' => function($postId, $post, $update) {
                        // Only handle new reports
                        if ($update || $post->post_type !== 'report') {
                            return null;
                        }
                        
                        return $this->transformer->toReportCreated($post);
                    }
                ]
            ],
            
            // Handle report updates
            ReportUpdated::class => [
                [
                    'action' => 'save_post',
                    'transformer' => function($postId, $post, $update) {
                        // Only handle report updates
                        if (!$update || $post->post_type !== 'report') {
                            return null;
                        }
                        
                        return $this->transformer->toReportUpdated($post);
                    }
                ]
            ]
        ];
    }
}
```

The beauty of this approach is that your internal application logic doesn't need to know about how events are emitted,
it just needs to listen for the nomadic events and it can do the actions from there. So if you needed to send an email
when a report is created, you could create an [event listener](event-listeners.md) in your a platform-agnostic
initializer that would fire when the report is published.

In doing so, you've completely decoupled the platform from your application - as long as the platform knows how to emit
the right events, and can translate them appropriately, it's compatible with your system.

Notice that the code below does not call any WordPress code whatsoever, and yet because of our event binding, we know
it will run when the report post is published. If we put this on a different platform, it's just a matter of sending
the `ReportCreated` event at the right time, and this would just work.

```php
class SendReportToCustomer implements CanHandle
{
    public function __construct(protected EmailStrategy $emailer){}
    
    public function handle(Event $event): void
    {
        $this->emailer->send(/** Include email details here. */)
    }
}

class ApplicationInitializer implements HasListeners
{
    public function getListeners(): void
    {
        return [
            ReportCreated::class => SendReportToCustomer::class
        ];
    }
}
```

## Summary

Event bindings are a powerful way to keep your application's core logic clean while still integrating smoothly with any
platform. They act as a bridge between your application and the platform, translating events back and forth without
cluttering your main code with platform-specific details.

Remember:

- Keep transformers simple and focused
- Handle errors gracefully
- Document platform events clearly
- Use service classes for complex transformations

With event bindings, your application can easily "speak" to any platform while keeping its own code clean and portable.