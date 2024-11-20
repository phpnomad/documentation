# Creating and Managing Initializers

Initializers are the building blocks of a PHPNomad application. Think of them as specialized workers, each with a
specific job to do when your application starts up. Each initializer handles one aspect of getting your application
ready to run - whether that's setting up database connections, loading configuration files, or connecting to external
services.

## What Makes Up an Initializer?

At its simplest, an initializer is a PHP class that implements one or more special interfaces. These interfaces tell
PHPNomad what the application can do:

```php
class MyInitializer implements HasClassDefinitions 
{
    public function getClassDefinitions(): array
    {
        return [
            // Define your class bindings here
            MyConcreteClass::class => MyInterface::class
        ];
    }
}
```

### Core Interfaces

PHPNomad supports several interfaces that give initializers different capabilities:

- `HasClassDefinitions`: For binding interfaces to concrete implementations
- `HasEventBindings`: For setting up event listeners and transformers
- `HasListeners`: For registering event listeners
- `Loadable`: For running code during initialization
- `CanSetContainer`: Gives your initializer access to the dependency container

## Types of Initializers

### Shared Initializers

These initializers contain core business logic that works anywhere. They should never know about specific platforms -
their job is to set up the fundamental pieces of your application:

```php
class EmailServiceInitializer implements HasClassDefinitions
{
    public function getClassDefinitions(): array
    {
        return [
            // Notice how this binds generic email interfaces
            // with no knowledge of any specific platform
            SmtpMailer::class => EmailStrategy::class,
            EmailTemplateEngine::class => TemplateRenderer::class
        ];
    }
}
```

### Platform Integration Initializers

These initializers handle adapting your application for specific platforms. Instead of adding platform checks to our
shared initializers, we create separate initializers that focus solely on platform integration:

```php
class WordPressEmailIntegration implements HasClassDefinitions
{
    public function getClassDefinitions(): array
    {
        return [
            // This initializer handles WordPress-specific bindings
            WordPressMailer::class => EmailStrategy::class
        ];
    }
}
```

### Event-Driven Initializers

Many initializers work with events to set up listeners and bindings:

```php
class UserEventsInitializer implements HasEventBindings
{
    public function getEventBindings(): array
    {
        return [
            UserCreated::class => [
                'user_register',
                ['transformer' => function($userId) {
                    return new UserCreated(new User($userId));
                }]
            ]
        ];
    }
}
```

## Best Practices

### Keep It Focused

Each initializer should do one thing and do it well. If you find your initializer handling multiple unrelated tasks,
it's time to split it up.

Good:

```php
class EmailServiceInitializer implements HasClassDefinitions
{
    public function getClassDefinitions(): array
    {
        return [
            SmtpMailer::class => EmailService::class
        ];
    }
}
```

Not so good:

```php
class ServiceInitializer implements HasClassDefinitions
{
    public function getClassDefinitions(): array
    {
        return [
            SmtpMailer::class => EmailService::class,
            MySqlDatabase::class => Database::class,
            RedisCache::class => CacheService::class
            // Too many unrelated services!
        ];
    }
}
```

### Handle Dependencies Wisely

Let the container manage dependencies instead of creating them directly:

```php
class UserServiceInitializer implements Loadable, CanSetContainer
{
    use HasSettableContainer;

    public function load(): void
    {
        // Let the container provide the dependency
        $userService = $this->container->get(UserService::class);
        $userService->initialize();
    }
}
```

## Common Patterns

### Setting Up Services

Register your services with the container:

```php
class ServiceInitializer implements HasClassDefinitions
{
    public function getClassDefinitions(): array
    {
        return [
            // Single binding
            ConcreteService::class => ServiceInterface::class,
            
            // Multiple interfaces
            DatabaseService::class => [
                QueryInterface::class,
                ConnectionInterface::class
            ]
        ];
    }
}
```

### Event Listeners

Set up event listeners in your initializer:

```php
class EventInitializer implements HasListeners
{
    public function getListeners(): array
    {
        return [
            UserCreated::class => UserCreatedHandler::class,
            OrderPlaced::class => [
                SendOrderConfirmation::class,
                UpdateInventory::class
            ]
        ];
    }
}
```

### Mutations

Register mutation handlers for transforming data:

```php
class DataMutationInitializer implements HasMutations
{
    public function getMutations(): array
    {
        return [
            UserData::class => [
                'sanitize_user_input',
                'validate_user_data'
            ]
        ];
    }
}
```

## Things to Avoid

1. **Don't Mix Platform-Specific Code**: Keep your shared initializers platform-agnostic.
2. **Avoid Direct Platform Dependencies**: Use interfaces instead of concrete platform classes.
3. **Don't Overuse Loadable::load**: Save it for truly necessary initialization tasks.
4. **Keep Initializers Small**: If an initializer is doing too much, split it up.

## Real-World Example

Here's a complete example of setting up authentication in a way that works across platforms:

```php
// Core authentication setup
class AuthenticationInitializer implements HasClassDefinitions, Loadable, CanSetContainer
{
    use HasSettableContainer;

    public function getClassDefinitions(): array
    {
        return [
            // Core authentication services
            AuthenticationService::class => AuthenticationInterface::class,
            TokenGenerator::class => TokenGeneratorInterface::class,
            
            // Multiple interface bindings
            UserRepository::class => [
                UserRepositoryInterface::class,
                IdentityStoreInterface::class
            ]
        ];
    }

    public function load(): void
    {
        // One-time setup tasks
        $authService = $this->container->get(AuthenticationInterface::class);
        $authService->initialize();
    }
}

// WordPress-specific authentication integration
class WordPressAuthIntegration implements HasClassDefinitions
{
    public function getClassDefinitions(): array
    {
        return [
            WordPressAuthProvider::class => AuthProviderInterface::class
        ];
    }
}
```

Remember, good initializers are like good tools - they do one job well, work reliably, and make your life easier. By
following these patterns and practices, you'll build a foundation that's easy to maintain and adapt as your needs
change.

The key to success with PHPNomad initializers is maintaining a clear separation between your core business logic and
platform-specific integrations. This separation allows your application to remain truly portable while still integrating
smoothly with any platform you need to support.