# Advanced Bootstrapping Patterns

When your application grows, you might need more sophisticated ways to organize how it starts up. This guide will show
you how to handle more complex bootstrapping scenarios in PHPNomad.

## Breaking Things into Groups

Just like you might organize your clothes into different drawers, you can organize your application's startup code into
logical groups. This makes everything easier to manage and understand.

Here's a simple example:

```php
// Database-related initialization
$databaseBootstrapper = new Bootstrapper(
    $container,
    new MySqlInitializer(),
    new TableCreateInitializer(),
    new MigrationInitializer()
);

// Authentication-related initialization
$authBootstrapper = new Bootstrapper(
    $container,
    new UserInitializer(),
    new PermissionInitializer(),
    new SessionInitializer()
);

// Run them in sequence
$databaseBootstrapper->load();
$authBootstrapper->load();
```

## Using Factories for Reusability

Sometimes you'll want to reuse the same group of initializers in different places. Factories are a great way to package
up these groups so they're easy to reuse:

```php
class DatabaseBootstrapperFactory 
{
    protected Container $container;
    
    public function __construct(Container $container) 
    {
        $this->container = $container;
    }
    
    public function create(): Bootstrapper 
    {
        return new Bootstrapper(
            $this->container,
            new MySqlInitializer(),
            new TableCreateInitializer(),
            new MigrationInitializer()
        );
    }
}

// Using the factory
$factory = new DatabaseBootstrapperFactory($container);
$bootstrapper = $factory->create();
$bootstrapper->load();
```

## Conditional Bootstrapping

Sometimes you need different initialization based on your environment or other conditions. Here's how you might handle
that:

```php
class ConditionalBootstrapper 
{
    public function load(string $environment) 
    {
        $container = new Container();
        
        // Core bootstrapping that always runs
        $core = new Bootstrapper(
            $container,
            new CoreInitializer(),
            new ConfigInitializer()
        );
        $core->load();
        
        // Environment-specific bootstrapping
        if ($environment === 'development') {
            $dev = new Bootstrapper(
                $container,
                new DebugInitializer(),
                new MockDataInitializer()
            );
            $dev->load();
        }
        
        if ($environment === 'production') {
            $prod = new Bootstrapper(
                $container,
                new CacheInitializer(),
                new MonitoringInitializer()
            );
            $prod->load();
        }
    }
}
```

## Real-World Example: Plugin System

Here's a practical example showing how you might bootstrap a plugin system:

```php
class PluginBootstrapperFactory 
{
    protected Container $container;
    
    public function __construct(Container $container) 
    {
        $this->container = $container;
    }
    
    public function createForPlugin(string $pluginName): Bootstrapper 
    {
        // Core plugin bootstrapping
        $bootstrapper = new Bootstrapper(
            $this->container,
            new PluginCoreInitializer($pluginName),
            new PluginAssetsInitializer($pluginName)
        );
        
        // Add optional features based on plugin configuration
        if ($this->hasDatabase($pluginName)) {
            $bootstrapper = new Bootstrapper(
                $this->container,
                $bootstrapper,
                new PluginDatabaseInitializer($pluginName)
            );
        }
        
        if ($this->hasRestApi($pluginName)) {
            $bootstrapper = new Bootstrapper(
                $this->container,
                $bootstrapper,
                new PluginRestInitializer($pluginName)
            );
        }
        
        return $bootstrapper;
    }
    
    protected function hasDatabase(string $pluginName): bool 
    {
        // Check if plugin needs database features
        return true; // Simplified for example
    }
    
    protected function hasRestApi(string $pluginName): bool 
    {
        // Check if plugin needs REST API features
        return true; // Simplified for example
    }
}
```

## Tips for Complex Bootstrapping

1. **Keep It Organized**: Group related initializers together. This makes your code easier to understand and maintain.

2. **Use Clear Names**: Give your bootstrapper groups and factories names that clearly explain what they do.

3. **Stay Flexible**: Design your bootstrapping code so it's easy to add or remove features without breaking things.

4. **Think About Order**: Sometimes the order of initialization matters. Group your bootstrappers accordingly and
   document any important ordering requirements.

## Common Patterns to Avoid

1. **Don't Mix Concerns**: Keep platform-specific code separate from your core bootstrapping logic.

2. **Avoid Global State**: Don't rely on global variables or static properties for bootstrapping configuration.

3. **Don't Over-Engineer**: Start simple and add complexity only when you need it.

## Summary

Advanced bootstrapping patterns help you manage complex applications while keeping your code organized and maintainable.
By using groups, factories, and conditional loading, you can create a flexible system that grows with your needs while
staying clean and understandable.

Remember: The goal is to make your initialization process clear and maintainable, not to make it clever or complex. When
in doubt, choose the simpler approach.