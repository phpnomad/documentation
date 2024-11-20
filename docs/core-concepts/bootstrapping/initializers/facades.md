# Creating and Using Facades in PHPNomad

While the Facade pattern was popularized by Laravel, PHPNomad's implementation takes a slightly different approach that
aligns with its platform-agnostic philosophy. Instead of being deeply integrated with a service container, PHPNomad's
Facades act as singleton wrappers that make your services easier to use across different platforms.

## What is a Facade?

Think of a Facade as a simple front door to a complex building. Instead of navigating through all the rooms and
hallways, you just walk through the front door to get where you need to go. In code terms, a Facade provides a clean,
static interface to access functionality that might otherwise require more complex setup or dependency management.

## Creating Your First Facade

Here's how to create a basic Facade:

```php
use PHPNomad\Facade\Abstracts\Facade;
use PHPNomad\Singleton\Traits\WithInstance;

/**
 * @method static void info(string $message)
 * @method static void error(string $message, array $context = [])
 */
class Logger extends Facade 
{
    use WithInstance; // Important! This makes the Facade a singleton

    protected function abstractInstance(): string 
    {
        return LoggerStrategy::class;
    }
}
```

## Using Your Facade

Once created, using your Facade is straightforward:

```php
// No need to instantiate - just use it!
Logger::instance()->getContainedInstance()->info("Hello from PHPNomad!");

// Or better yet, add static methods to make it cleaner:
Logger::info("Hello from PHPNomad!");
```

## Key Differences from Laravel

While inspired by Laravel's Facades, PHPNomad's implementation has some key differences:

1. **Singleton Pattern**: PHPNomad Facades use the `WithInstance` trait to ensure only one instance exists
2. **Platform Independence**: PHPNomad's Facades are designed to work across any PHP platform
3. **Explicit Methods**: Instead of magic methods, PHPNomad encourages explicitly defining the methods you want to
   expose

## Registering Facades

Facades need to be registered with PHPNomad through an initializer:

```php
use PHPNomad\Di\Interfaces\CanSetContainer;
use PHPNomad\Di\Traits\HasSettableContainer;
use PHPNomad\Facade\Interfaces\HasFacades;

class CoreInitializer implements HasFacades, HasClassDefinitions, CanSetContainer 
{
    use HasSettableContainer;

    public function getFacades(): array 
    {
        return [
            Logger::instance(), // Note the instance() call
            Cache::instance(),
            Event::instance()
        ];
    }

    public function getClassDefinitions(): array 
    {
        return [
            LogService::class => LoggerStrategy::class,
            RedisCache::class => CacheStrategy::class,
            EventDispatcher::class => EventStrategy::class
        ];
    }
}
```

## A Complete Example

Here's a complete example showing how to create and use a Cache Facade:

```php
/**
 * 1. Create the Facade
 */
class Cache extends Facade 
{
    use WithInstance;

    protected function abstractInstance(): string 
    {
        return CacheStrategy::class;
    }

    public static function get(string $key): mixed 
    {
        return static::instance()->getContainedInstance()->get($key);
    }

    public static function set(string $key, mixed $value, ?int $ttl = null): void 
    {
        static::instance()->getContainedInstance()->set($key, $value, $ttl);
    }

    public static function has(string $key): bool 
    {
        return static::instance()->getContainedInstance()->has($key);
    }
}

/**
 * 2. Create the Initializer
 */
class CacheInitializer implements HasFacades, HasClassDefinitions, CanSetContainer 
{
    use HasSettableContainer;

    public function getFacades(): array 
    {
        return [
            Cache::instance() // Single instance
        ];
    }

    public function getClassDefinitions(): array 
    {
        return [
            RedisCache::class => CacheStrategy::class
        ];
    }
}

/**
 * 3. Bootstrap your application
 */
$container = new Container();
$bootstrapper = new Bootstrapper(
    $container,
    new CacheInitializer()
);
$bootstrapper->load();

/**
 * 4. Use the Facade anywhere in your code
 */
Cache::set('my-key', 'my-value', 3600);
$value = Cache::get('my-key');
```

## ⚠️ Important Warning About Facades

Before diving into how to create and use Facades, it's crucial to understand their intended purpose and limitations:
Facades in PHPNomad are primarily designed for:

* Creating public APIs that third-party developers can easily consume
* Providing simple access points for developers integrating with your application from outside the PHPNomad context
* Situations where dependency injection isn't practical (like static WordPress hooks or template files)

They are not intended for:

* Regular application development within your PHPNomad codebase
* Replacing proper dependency injection
* Avoiding proper service architecture

If you find yourself frequently using Facades within your own application code, this is usually a sign that you should
reconsider your approach. Instead, use dependency injection and proper service architecture for better maintainability,
testability, and cleaner code design.

Example of Proper vs. Improper Use
```php
// 🚫 Don't do this in your application code
class UserService {
    public function createUser(array $data) {
        Logger::info("Creating user...");  // Directly using Facade
        // ... rest of the code
    }
}

// ✅ Do this instead
class UserService {
    private LoggerStrategy $logger;
    
    public function __construct(LoggerStrategy $logger) {
        $this->logger = $logger;  // Proper dependency injection
    }
    
    public function createUser(array $data) {
        $this->logger->info("Creating user...");
        // ... rest of the code
    }
}

// ✅ Facades are great for third-party integration points
add_action('init', function() {
    Logger::info("WordPress integration point");
});
```

## Best Practices

### 1. Always Use WithInstance

Every Facade must use the `WithInstance` trait to ensure the singleton pattern:

```php
class AnyFacade extends Facade 
{
    use WithInstance; // Don't forget this!
}
```

### 2. Keep Facades Focused

Each Facade should represent a single service:

```php
// Good - focused on caching
class Cache extends Facade 
{
    use WithInstance;

    protected function abstractInstance(): string 
    {
        return CacheStrategy::class;
    }
}

// Not good - mixing concerns
class Utilities extends Facade 
{
    use WithInstance;

    protected function abstractInstance(): string 
    {
        return UtilityService::class; // Too many responsibilities
    }
}
```

### 3. Group Related Facades in Initializers

Keep related Facades together in their initializers:

```php
class DatabaseInitializer implements HasFacades 
{
    public function getFacades(): array 
    {
        return [
            Query::instance(),
            Schema::instance(),
            Migration::instance()
        ];
    }
}
```

## When to Use Facades

Facades are great for:

- Services that need global access
- Core functionality used throughout your application
- Cross-cutting concerns like logging, caching, and events

Consider alternatives when:

- The service requires complex configuration
- You're writing unit tests (use dependency injection)
- The functionality is specific to a single module

## Common Pitfalls to Avoid

1. **Forgetting WithInstance**: Always include the `WithInstance` trait in your Facades
2. **Overusing Facades**: Not everything needs to be a Facade
3. **Complex Logic in Facades**: Keep Facade methods simple and delegate complex logic to the service
4. **Bypassing Dependency Injection**: Use Facades for convenience, not to avoid proper dependency management

Remember: Facades in PHPNomad combine the singleton pattern with static access to provide convenient, globally
accessible services while maintaining the flexibility to work across different platforms. The key is to use them
thoughtfully and always remember to include the `WithInstance` trait.