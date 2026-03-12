---
id: config-service-config-service
slug: docs/packages/config/services/config-service
title: ConfigService Class
doc_type: reference
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: The ConfigService class orchestrates loading configuration files and registering them with a strategy.
llm_summary: >
  ConfigService is the orchestration class that connects ConfigFileLoaderStrategy and ConfigStrategy.
  It takes both as constructor dependencies and provides registerConfig(string $key, string $path)
  to load a file and register it under a namespace. The method returns $this for fluent chaining.
  Simplifies the common pattern of loading multiple configuration files at application bootstrap.
questions_answered:
  - What is ConfigService?
  - How do I load configuration files?
  - How do I use ConfigService with dependency injection?
  - How do I load multiple configuration files?
audience:
  - developers
  - backend engineers
tags:
  - service
  - config
  - orchestration
llm_tags:
  - config-service
  - file-loading
  - orchestration
keywords:
  - ConfigService class
  - configuration orchestration
  - file registration
related:
  - ../introduction
  - ../interfaces/config-strategy
see_also:
  - ../interfaces/config-file-loader-strategy
  - ../exceptions/config-exception
noindex: false
---

# ConfigService Class

The `ConfigService` class **orchestrates** loading configuration files and registering them with a strategy. It connects the file loader and storage backend.

## Class definition

```php
namespace PHPNomad\Config\Services;

use PHPNomad\Config\Interfaces\ConfigStrategy;
use PHPNomad\Config\Interfaces\ConfigFileLoaderStrategy;

class ConfigService
{
    public function __construct(
        protected ConfigStrategy $configStrategy,
        protected ConfigFileLoaderStrategy $configFileLoaderStrategy
    ) {}

    public function registerConfig(string $key, string $path): static
    {
        $configs = $this->configFileLoaderStrategy->loadFileConfigs($path);
        $this->configStrategy->register($key, $configs);
        return $this;
    }
}
```

## Constructor

### `__construct(ConfigStrategy $configStrategy, ConfigFileLoaderStrategy $configFileLoaderStrategy)`

**Parameters:**
- `$configStrategy` — The storage backend for configuration data
- `$configFileLoaderStrategy` — The file format loader

## Methods

### `registerConfig(string $key, string $path): static`

Loads a configuration file and registers it under a namespace.

**Parameters:**
- `$key` — The namespace to register under (e.g., `'database'`)
- `$path` — Absolute path to the configuration file

**Returns:** `static` — For fluent chaining

**Throws:** `ConfigException` — If file loading fails

---

## Basic usage

```php
use PHPNomad\Config\Services\ConfigService;

// Create dependencies
$strategy = new ArrayConfig();
$loader = new PhpConfigLoader();

// Create service
$service = new ConfigService($strategy, $loader);

// Load a configuration file
$service->registerConfig('database', '/app/config/database.php');

// Access via strategy
$host = $strategy->get('database.host');
```

---

## Fluent chaining

Load multiple files with chained calls:

```php
$service
    ->registerConfig('app', __DIR__ . '/config/app.php')
    ->registerConfig('database', __DIR__ . '/config/database.php')
    ->registerConfig('cache', __DIR__ . '/config/cache.php')
    ->registerConfig('mail', __DIR__ . '/config/mail.php')
    ->registerConfig('queue', __DIR__ . '/config/queue.php');
```

---

## With dependency injection

Register in your DI container:

```php
// Registration
$container->set(ConfigStrategy::class, fn() => new ArrayConfig());

$container->set(ConfigFileLoaderStrategy::class, fn() => new PhpConfigLoader());

$container->set(ConfigService::class, fn($c) => new ConfigService(
    $c->get(ConfigStrategy::class),
    $c->get(ConfigFileLoaderStrategy::class)
));

// Usage
$configService = $container->get(ConfigService::class);
$configService->registerConfig('app', '/config/app.php');
```

---

## Environment-aware loading

Load base configuration plus environment overrides:

```php
class ConfigLoader
{
    public function __construct(
        private ConfigService $service,
        private string $configDir,
        private string $environment
    ) {}

    public function loadAll(): void
    {
        $files = ['app', 'database', 'cache', 'mail'];

        foreach ($files as $file) {
            // Load base config
            $basePath = "{$this->configDir}/{$file}.php";
            if (file_exists($basePath)) {
                $this->service->registerConfig($file, $basePath);
            }

            // Load environment override
            $envPath = "{$this->configDir}/{$this->environment}/{$file}.php";
            if (file_exists($envPath)) {
                $this->service->registerConfig($file, $envPath);
            }
        }
    }
}

// Usage
$loader = new ConfigLoader(
    $configService,
    __DIR__ . '/config',
    $_ENV['APP_ENV'] ?? 'production'
);
$loader->loadAll();
```

Directory structure:

```
config/
├── app.php           # Base configuration
├── database.php
├── development/
│   ├── app.php       # Development overrides
│   └── database.php
└── production/
    └── database.php  # Production overrides
```

---

## Error handling

```php
use PHPNomad\Config\Exceptions\ConfigException;

try {
    $service->registerConfig('app', '/path/to/app.php');
} catch (ConfigException $e) {
    // Log the error
    error_log("Configuration error: " . $e->getMessage());

    // Use fallback configuration
    $strategy->register('app', [
        'name' => 'Default App',
        'debug' => false,
    ]);
}
```

---

## Conditional loading

Load configuration based on conditions:

```php
class ConditionalConfigLoader
{
    public function __construct(
        private ConfigService $service,
        private string $configDir
    ) {}

    public function load(): void
    {
        // Always load core config
        $this->service->registerConfig('app', "{$this->configDir}/app.php");

        // Load database config only if needed
        if ($this->needsDatabase()) {
            $this->service->registerConfig('database', "{$this->configDir}/database.php");
        }

        // Load cache config only if cache is enabled
        if (getenv('CACHE_ENABLED') === 'true') {
            $this->service->registerConfig('cache', "{$this->configDir}/cache.php");
        }
    }

    private function needsDatabase(): bool
    {
        return getenv('DATABASE_URL') !== false;
    }
}
```

---

## Best practices

### Load at bootstrap

Load all configuration early in your application lifecycle:

```php
// In bootstrap.php or Application::__construct()
$configService
    ->registerConfig('app', $configDir . '/app.php')
    ->registerConfig('database', $configDir . '/database.php');

// Configuration is now available throughout the application
```

### Use absolute paths

```php
// Good: absolute path
$service->registerConfig('app', __DIR__ . '/config/app.php');

// Bad: relative path (depends on working directory)
$service->registerConfig('app', 'config/app.php');
```

### Keep the service internal

Applications should access configuration via `ConfigStrategy`, not `ConfigService`:

```php
// Good: inject the strategy for reading
class MyService
{
    public function __construct(private ConfigStrategy $config) {}

    public function doSomething(): void
    {
        $timeout = $this->config->get('api.timeout', 30);
    }
}

// Bad: inject the service just to read config
class MyService
{
    public function __construct(private ConfigService $service) {}
    // ConfigService is for loading, not reading
}
```

---

## Testing

```php
class ConfigServiceTest extends TestCase
{
    public function test_loads_and_registers_config(): void
    {
        $strategy = $this->createMock(ConfigStrategy::class);
        $loader = $this->createMock(ConfigFileLoaderStrategy::class);

        $loader->expects($this->once())
            ->method('loadFileConfigs')
            ->with('/path/to/config.php')
            ->willReturn(['key' => 'value']);

        $strategy->expects($this->once())
            ->method('register')
            ->with('app', ['key' => 'value']);

        $service = new ConfigService($strategy, $loader);
        $service->registerConfig('app', '/path/to/config.php');
    }

    public function test_returns_self_for_chaining(): void
    {
        $strategy = new ArrayConfig();
        $loader = $this->createMock(ConfigFileLoaderStrategy::class);
        $loader->method('loadFileConfigs')->willReturn([]);

        $service = new ConfigService($strategy, $loader);

        $result = $service->registerConfig('app', '/path');

        $this->assertSame($service, $result);
    }
}
```

---

## See also

- [ConfigStrategy](../interfaces/config-strategy) — The storage interface
- [ConfigFileLoaderStrategy](../interfaces/config-file-loader-strategy) — The file loader interface
- [ConfigException](../exceptions/config-exception) — Error handling
