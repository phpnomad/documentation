---
id: config-interface-config-strategy
slug: docs/packages/config/interfaces/config-strategy
title: ConfigStrategy Interface
doc_type: reference
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: The ConfigStrategy interface defines how configuration data is stored, retrieved, and checked using dot-notation keys.
llm_summary: >
  The ConfigStrategy interface is the main contract for configuration management in phpnomad/config.
  It defines three methods: register(string $key, array $configData) for storing configuration sections,
  has(string $key) for checking existence, and get(string $key, $default) for retrieval. Supports
  dot-notation for nested access like 'database.credentials.username'. Implementations can use
  in-memory arrays, databases, Redis, or any storage backend.
questions_answered:
  - What is ConfigStrategy?
  - How do I store configuration data?
  - How do I retrieve configuration with dot-notation?
  - How do I implement ConfigStrategy?
  - What storage backends can I use?
audience:
  - developers
  - backend engineers
tags:
  - interface
  - config
  - strategy
llm_tags:
  - config-strategy
  - dot-notation
  - configuration-storage
keywords:
  - ConfigStrategy interface
  - configuration storage
  - dot notation
  - nested configuration
related:
  - ../introduction
  - ../services/config-service
see_also:
  - config-file-loader-strategy
  - ../exceptions/config-exception
noindex: false
---

# ConfigStrategy Interface

The `ConfigStrategy` interface defines how configuration data is **stored and retrieved**. It's the main interface applications interact with for configuration access.

## Interface definition

```php
namespace PHPNomad\Config\Interfaces;

interface ConfigStrategy
{
    /**
     * Registers a top-level set of configuration data.
     */
    public function register(string $key, array $configData);

    /**
     * Checks if a configuration key exists.
     */
    public function has(string $key): bool;

    /**
     * Gets a configuration value by dot-notated key.
     */
    public function get(string $key, $default = null);
}
```

## Methods

### `register(string $key, array $configData): static`

Stores a section of configuration under a namespace.

**Parameters:**
- `$key` — The top-level namespace (e.g., `'database'`, `'cache'`)
- `$configData` — The configuration array to store

**Returns:** `static` — For fluent chaining

**Example:**
```php
$config->register('database', [
    'host' => 'localhost',
    'port' => 3306,
    'credentials' => [
        'username' => 'app_user',
        'password' => 'secret'
    ]
]);
```

### `has(string $key): bool`

Checks if a configuration key exists.

**Parameters:**
- `$key` — Dot-notated key to check

**Returns:** `bool` — True if key exists, false otherwise

**Example:**
```php
$config->has('database.host');              // true
$config->has('database.credentials.username'); // true
$config->has('database.nonexistent');       // false
```

### `get(string $key, $default = null): mixed`

Retrieves a configuration value by dot-notated key.

**Parameters:**
- `$key` — Dot-notated key (e.g., `'database.credentials.username'`)
- `$default` — Value to return if key doesn't exist

**Returns:** The configuration value, or default if not found

**Example:**
```php
$config->get('database.host');              // 'localhost'
$config->get('database.timeout', 30);       // 30 (default)
$config->get('database.credentials');       // ['username' => '...', 'password' => '...']
```

---

## Dot-notation access

Dot-notation lets you access nested configuration:

```php
$config->register('app', [
    'name' => 'MyApp',
    'database' => [
        'primary' => [
            'host' => 'db1.example.com',
            'port' => 3306
        ],
        'replica' => [
            'host' => 'db2.example.com',
            'port' => 3306
        ]
    ]
]);

// Access nested values
$config->get('app.name');                    // 'MyApp'
$config->get('app.database.primary.host');   // 'db1.example.com'
$config->get('app.database.replica');        // ['host' => '...', 'port' => 3306]
```

---

## Basic implementation

Here's a complete in-memory implementation:

```php
use PHPNomad\Config\Interfaces\ConfigStrategy;

class ArrayConfig implements ConfigStrategy
{
    private array $data = [];

    public function register(string $key, array $configData): static
    {
        $this->data[$key] = $configData;
        return $this;
    }

    public function has(string $key): bool
    {
        return $this->resolve($key) !== null;
    }

    public function get(string $key, $default = null)
    {
        return $this->resolve($key) ?? $default;
    }

    private function resolve(string $key): mixed
    {
        $parts = explode('.', $key);
        $value = $this->data;

        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return null;
            }
            $value = $value[$part];
        }

        return $value;
    }
}
```

---

## With merge support

Allow configuration overrides by merging:

```php
class MergeableConfig implements ConfigStrategy
{
    private array $data = [];

    public function register(string $key, array $configData): static
    {
        if (isset($this->data[$key])) {
            // Deep merge with existing
            $this->data[$key] = $this->deepMerge(
                $this->data[$key],
                $configData
            );
        } else {
            $this->data[$key] = $configData;
        }
        return $this;
    }

    private function deepMerge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->deepMerge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }

    // ... has() and get() same as ArrayConfig
}
```

Usage for environment overrides:

```php
// Base configuration
$config->register('database', [
    'host' => 'localhost',
    'port' => 3306,
    'debug' => false
]);

// Production override (merges)
$config->register('database', [
    'host' => 'db.production.com',
    'debug' => false
]);

$config->get('database.host');  // 'db.production.com'
$config->get('database.port');  // 3306 (preserved from base)
```

---

## Alternative backends

### Environment-based

```php
class EnvConfig implements ConfigStrategy
{
    private array $prefixes = [];

    public function register(string $key, array $configData): static
    {
        // Store prefix mapping for environment variable lookup
        $this->prefixes[$key] = strtoupper($key);
        return $this;
    }

    public function has(string $key): bool
    {
        return getenv($this->toEnvKey($key)) !== false;
    }

    public function get(string $key, $default = null)
    {
        $value = getenv($this->toEnvKey($key));
        return $value !== false ? $value : $default;
    }

    private function toEnvKey(string $key): string
    {
        // database.host -> DATABASE_HOST
        return strtoupper(str_replace('.', '_', $key));
    }
}
```

### Cached

```php
class CachedConfig implements ConfigStrategy
{
    private array $cache = [];

    public function __construct(
        private ConfigStrategy $inner,
        private CacheInterface $cacheBackend
    ) {}

    public function get(string $key, $default = null)
    {
        if (!isset($this->cache[$key])) {
            $this->cache[$key] = $this->cacheBackend->get(
                "config:{$key}",
                fn() => $this->inner->get($key, $default)
            );
        }
        return $this->cache[$key];
    }

    // ... other methods delegate to $inner
}
```

---

## Best practices

### Use clear namespaces

```php
// Good: organized by domain
$config->register('database', [...]);
$config->register('cache', [...]);
$config->register('mail', [...]);

// Bad: everything flat
$config->register('settings', [
    'db_host' => '...',
    'cache_ttl' => '...',
    'mail_from' => '...'
]);
```

### Provide sensible defaults

```php
// In your application code
$timeout = $config->get('api.timeout', 30);
$retries = $config->get('api.retries', 3);
```

### Validate early

Check required configuration at bootstrap:

```php
$required = ['database.host', 'app.secret_key'];
foreach ($required as $key) {
    if (!$config->has($key)) {
        throw new ConfigException("Missing: {$key}");
    }
}
```

---

## Testing

```php
class ArrayConfigTest extends TestCase
{
    public function test_registers_and_retrieves_config(): void
    {
        $config = new ArrayConfig();
        $config->register('app', ['name' => 'Test']);

        $this->assertEquals('Test', $config->get('app.name'));
    }

    public function test_returns_default_for_missing_key(): void
    {
        $config = new ArrayConfig();

        $this->assertEquals('default', $config->get('missing.key', 'default'));
    }

    public function test_has_returns_true_for_existing_key(): void
    {
        $config = new ArrayConfig();
        $config->register('app', ['debug' => true]);

        $this->assertTrue($config->has('app.debug'));
        $this->assertFalse($config->has('app.nonexistent'));
    }

    public function test_supports_deep_nesting(): void
    {
        $config = new ArrayConfig();
        $config->register('a', ['b' => ['c' => ['d' => 'value']]]);

        $this->assertEquals('value', $config->get('a.b.c.d'));
    }
}
```

---

## See also

- [ConfigFileLoaderStrategy](config-file-loader-strategy) — Loading configuration from files
- [ConfigService](../services/config-service) — Orchestrating file loading and registration
- [ConfigException](../exceptions/config-exception) — Error handling
