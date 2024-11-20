# Event Listeners in PHPNomad

Event listeners are like friendly observers in your code that wait for specific things to happen, and then spring into
action when they do. Think of them as helpful assistants who are always ready to respond when something important occurs
in your application.

Events are the bread and butter of PHPNomad, and it leans heavily on using them to keep your system decoupled from the
platform. In-fact, most of the time, you'll find that your entire system will boil down to a series of events that fire,
and then some logic that happens when those actions happen. If you can get very comfortable with thinking about
event-driven approaches, you'll come to love how PHPNomad works.

## The Basics of Event Listeners

At their core, event listeners are just special classes that "listen" for specific events in your application. When
those events happen, the listeners automatically run their code. Each listener is instantiated through PHPNomad's
container, which means you can easily inject any dependencies you need.

Here's a simple example:

```php
use PHPNomad\Email\Interfaces\EmailStrategy;
use PHPNomad\Events\Interfaces\Event;

class UserRegistered implements Event
{
    public function __construct(public readonly string $email, public readonly string $name){}
    
    
    public static function getId(): string
    {
        return 'user_registered';
    }
}

/**
 * @extends CanHandle<UserRegistered>
 */
class WelcomeEmailListener implements CanHandle
{
    public function __construct(private EmailStrategy $emailService) 
    {
        // PHPNomad automatically injects the email service
    }

    public function handle(Event $event): void 
    {
        // Send a welcome email when a new user registers
        $this->emailService->send(
            [$event->email],
            'Welcome to Our Platform!',
            'welcome-email-template',
            ['username' => $event->name]
        );
    }
}
```

## Setting Up Listeners

You can set up listeners in your initializer class. This is where you tell PHPNomad "when this happens, run this code":

```php
class MyInitializer implements HasListeners 
{
    public function getListeners(): array
    {
        return [
            // When a user gets registered, send a welcome email.
            UserRegistered::class => WelcomeEmailListener::class,
            
            // When an order is placed, send the order confirmation and also update the inventory
            OrderPlaced::class => [
                SendOrderConfirmation::class,
                UpdateInventory::class
            ]
        ];
    }
}
```

In this example:

- When a user registers, the container creates a WelcomeEmailListener (injecting the EmailStrategy) and runs its handle
  method
- When an order is placed, multiple listeners are created and executed in sequence

Now, when you broadcast the `UserRegistered` event, the `WelcomeEmailListener` will fire.

```php
Event::broadcast(new UserRegistered('alex@fake.email','Alex'));
```

Alternatively, you might need to create an event binding that translates a platform event (such as a WordPress hook) to
the `UserRegistered` event. Check out [Event Binding](event-binding.md) for more context on that.

## Why Use Event Listeners?

Event listeners help keep your code organized and flexible. Instead of putting all your logic in one place, you can
spread it out into focused, manageable pieces. This brings several benefits:

1. **Easier to Maintain**: Each listener handles one specific task, making the code simpler to understand and update
2. **More Flexible**: You can add or remove listeners without changing the rest of your code
3. **Better Organization**: Related code stays together, making it easier to find and modify
4. **Dependency Management**: PHPNomad's container handles all dependencies automatically

## Real World Example

Let's look at a practical example. Imagine you're running an online store and someone places an order:

```php
use PHPNomad\Email\Interfaces\EmailStrategy;

/**
 * @extends CanHandle<OrderPlaced>
 */
class SendOrderConfirmation implements CanHandle
{
    public function __construct(
        private EmailStrategy $emailService
    ) {}

    public function handle(Event $event): void
    {
        $order = $event->getOrder();

        $this->emailService->send(
            [$order->getCustomerEmail()],
            'Order Confirmation',
            'order-confirmation-template',
            ['orderNumber' => $order->getId()]
        );
    }
}

/**
 * @extends CanHandle<OrderPlaced>
 */
class UpdateInventory implements CanHandle
{
    public function __construct(
        private InventoryService $inventory
    ) {
        // Dependencies are injected automatically
    }

    public function handle(Event $event): void
    {
         $order = $event->getOrder();

         // Update inventory
         $this->inventory->reduceStock($order->getItems());
    }
}
```

## Best Practices

1. **Use Constructor Injection**: Let PHPNomad's container manage your dependencies by declaring them in your
   constructor:

```php
class WelcomeEmailListener implements CanHandle
{
    public function __construct(
        private EmailStrategy $emailService,
        private LoggerStrategy $logger
    ) {
    }

    public function handle(Event $event): void 
    {
        try {
            $this->emailService->send(
                [$event->getUser()->getEmail()],
                'Welcome!',
                'welcome-template',
                ['username' => $event->getUser()->getName()]
            );
        } catch (Exception $e) {
            $this->logger->error("Failed to send welcome email: " . $e->getMessage());
        }
    }
}
```

2. **Keep Listeners Focused**: Each listener should do one job well. If you find a listener doing too many things,
   consider splitting it into multiple listeners.

3. **Use Interfaces**: Always type-hint interfaces rather than concrete classes in your constructor. This keeps your
   code flexible and testable.

## Common Scenarios

Here are some typical situations where event listeners are particularly useful:

1. **User Actions**:

```php
class UserProfileCreationListener implements CanHandle
{
    public function __construct(
        private ProfileService $profiles,
        private LoggerStrategy $logger
    ) {
    }

    public function handle(Event $event): void 
    {
        $this->profiles->createDefaultProfile($event->getUser());
    }
}
```

2. **Order Processing**:

```php
class OrderPaymentListener implements CanHandle
{
    public function __construct(
        private PaymentService $payments,
        private LoggerStrategy $logger
    ) {
    }

    public function handle(Event $event): void 
    {
        $this->payments->processPayment($event->getOrder());
    }
}
```

## Troubleshooting

If your listeners aren't working as expected, check these common issues:

1. **Is the Event Being Triggered?**
   Make sure the event is actually being broadcast:
   ```php
   Event::broadcast(new UserRegistered($user));
   ```

2. **Is the Listener Registered?**
   Verify your listener is properly registered in your initializer.

## Conclusion

Event listeners in PHPNomad provide a clean, maintainable way to handle application events while taking full advantage
of dependency injection. By letting the container manage your dependencies, you create code that's not only easier to
maintain but also easier to test and modify. Each listener can focus on its specific task while having easy access to
any services it needs.

Think of event listeners as specialized workers in your application - each one has access to exactly the tools they
need (through dependency injection) and knows exactly what to do when certain events occur.