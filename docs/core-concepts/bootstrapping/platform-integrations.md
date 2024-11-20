# Introduction to Platform-Specific Integrations

When building applications with PHPNomad, you might need to integrate with platforms like WordPress, Laravel, or Symfony. These platforms often come with unique requirements, but PHPNomad’s philosophy ensures your core logic remains flexible and portable. The goal isn’t to lock your application into a specific platform but to allow your application to adapt seamlessly, as if it’s just visiting.

PHPNomad takes a "nomadic" approach: instead of the platform dictating how your application is built, your application integrates with the platform through lightweight, modular adapters. This perspective keeps your application’s core logic clean and reusable, whether you’re bootstrapping a WordPress plugin or setting up a Laravel service provider.

## Core Principles for Platform-Specific Integrations

To make integrations easy and maintainable, PHPNomad emphasizes separating shared application logic from platform-specific details. A shared initializer handles logic that works across platforms, while a platform-specific initializer adapts to the unique needs of a given environment.

For example, imagine initializing a WordPress plugin:

```php
$bootstrapper = new Bootstrapper(
    new Container(),
    new WordPressInitializer(), // Provided by PHPNomad, making most functionality to work in WordPress context.
    new ConfigInitializer(['config.json']),
    new ApplicationWordPressInitializer(),
    new ApplicationInitializer() // Business logic that's shared between platforms.
);

$bootstrapper->load();
```

Here, ConfigInitializer handles shared logic like loading configuration files, while ApplicationWordPressInitializer deals with WordPress-specific tasks, such as registering hooks. This clear division ensures your codebase remains portable, and swapping platforms later is as easy as replacing an initializer.

## Common Use Cases

A typical integration involves adapting the application’s bootstrapping process to a platform’s specific entry points. For WordPress, this might include setting up actions and filters, or binding WordPress actions with events that are inside your application.

```php
use PHPNomad\Events\Interfaces\HasEventBindings;
use PHPNomad\Events\Interfaces\HasListeners;
use PHPNomad\Core\Events\Ready;
use PHPNomad\Core\Events\Interfaces\CanHandle;

class ApplicationWordPressInitializer implements HasEventBindings, HasListeners
{
    /**
     * Bind WordPress hooks to trigger Ready event.
     *
     * @return array
     */
    public function getEventBindings(): array
    {
        return [
            // Bind WordPress 'init' hook to trigger the Ready event.
            Ready::class => [
                ['action' => 'init', 'transformer' => function () {
                    $ready = null;

                    if (!self::$initRan) {
                        $ready = new Ready();
                        self::$initRan = true;
                    }

                    return $ready;
                }]
            ],
        ];
    }

    /**
     * Register the listener for the Ready event.
     *
     * @return array
     */
    public function getListeners(): array
    {
        return [
            // Instruct the system to register custom post types using a custom RegisterCustomPostTypes::class handler.
            Ready::class => [RegisterCustomPostTypes::class]
        ];
    }
}
```

The plugin might look like:

```php
/**
 * Plugin Name: Demo Plugin
 */ 

require_once plugin_dir_url(__FILE__, 'vendor/autoload.php');

$bootstrapper = new Bootstrapper($container, [
    new ConfigInitializer(['config.json']),
    new MySqlDatabaseInitializer(),
    new RestApiInitializer(),
    new SymfonyEventInitializer(),
    new HomegrownSystemInitializer(),
    new ApplicationInitializer() // Business logic that's shared between platforms.
]);

// Run the bootstrapper to load all initializers.
$bootstrapper->load();
```

In a homegrown setup, you might not need the binding since you have control of the entire request, and can probably just emit the event after the system is set up.

```php
// index.php

require_once 'vendor/autoload.php';

use PHPNomad\Loader\Bootstrapper;
use PHPNomad\Core\Events\Ready;
use PHPNomad\Core\Facades\Event;
use PHPNomad\Core\Facades\Container;

// Set up the container and initialize the bootstrapper.
$container = new Container();
$bootstrapper = new Bootstrapper($container, [
    new ConfigInitializer(['config.json']),
    new ApplicationSpecificInitializer(),
    new HomegrownSystemInitializer(),
    new ApplicationInitializer() // Business logic that's shared between platforms.
]);

// Run the bootstrapper to load all initializers.
$bootstrapper->load();

// Broadcast the Ready event manually.
Event::broadcast(new Ready());
```

These integrations allow the application to interface with the platform without embedding platform-specific details directly into the shared logic. 

## Challenges and Best Practices

One common pitfall in platform-specific integrations is allowing platform logic to creep into shared initializers. For instance, a shared initializer should never directly reference WordPress-specific functions or Laravel facades. Keeping these concerns separate ensures that shared initializers remain reusable across different contexts.

If you’re debugging a platform-specific initializer, make sure the problem isn’t due to the platform’s lifecycle or API. For example, in WordPress, actions and filters may need to be registered at a specific point during initialization.

To avoid such issues, structure your code like this:

```php
class DebuggableWordPressInitializer implements Loadable {
    public function load(Container $container): void {
        add_action('plugins_loaded', [$this, 'onPluginsLoaded']);
    }

    public function onPluginsLoaded(): void {
        error_log('Plugins have been loaded');
    }
}
```

This example logs a message when all plugins are loaded, giving you insight into whether your initializer is being called at the right time.

## Expanding Integrations

PHPNomad encourages you to think modularly when creating platform-specific initializers. By keeping your logic well-organized, you’ll make it easy to reuse these initializers across projects. For example, if you’ve written a WordPress initializer for handling custom post types, consider extracting it into a standalone class or package that can be dropped into any future WordPress project.

Moreover, by sticking to PHPNomad’s philosophy of platform-agnostic design, your integrations will naturally be future-proof. As platforms evolve, you can update your initializers without rewriting your application’s core logic.

Integrating with platforms doesn’t have to mean sacrificing flexibility. With PHPNomad, you can build applications that are adaptable, maintainable, and ready to travel wherever your project leads. By keeping platform logic modular and separate from your core logic, you ensure your applications remain as free-spirited as the PHPNomad philosophy itself.