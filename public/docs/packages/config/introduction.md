---
id: config-introduction
slug: docs/packages/config/introduction
title: Config Package
doc_type: explanation
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: The config package provides a strategy-based configuration management system with dot-notation access and pluggable file loaders.
llm_summary: >
  phpnomad/config provides a flexible configuration management system using the strategy pattern.
  ConfigStrategy interface defines how configuration is stored and accessed (with dot-notation support).
  ConfigFileLoaderStrategy interface defines how config files are loaded from disk (supporting PHP, JSON,
  YAML, etc.). ConfigService orchestrates loading files and registering them with the strategy. The package
  has zero dependencies and is used by json-config-integration for JSON file support and wordpress-plugin
  for WordPress configuration. Supports nested configuration with dot-notation access like 'database.host'.
questions_answered:
  - What is the config package?
  - How do I manage configuration in PHPNomad?
  - How do I access nested configuration values?
  - What is dot-notation configuration access?
  - How do I load configuration from files?
  - How do I create a custom configuration loader?
  - How do I implement ConfigStrategy?
  - What packages use the config system?
audience:
  - developers
  - backend engineers
tags:
  - config
  - configuration
  - strategy-pattern
  - settings
llm_tags:
  - configuration-management
  - dot-notation
  - strategy-pattern
  - file-loading
keywords:
  - phpnomad config
  - configuration management php
  - ConfigStrategy
  - dot notation config
  - config file loader
related:
  - ../json-config-integration/introduction
  - ../di/introduction
see_also:
  - interfaces/introduction
  - services/introduction
  - ../core/introduction
noindex: false
---

# Config

`phpnomad/config` provides a **strategy-based configuration management system** for PHP applications. Instead of hardcoding how configuration is stored or loaded, the package uses interfaces that let you:

* **Choose your storage** — In-memory, database, Redis, or custom backends
* **Choose your file format** — PHP arrays, JSON, YAML, or any format you need
* **Access nested values** — Use dot-notation like `database.credentials.username`
* **Swap implementations** — Change strategies without touching application code

---

## Key ideas at a glance

| Component | Purpose | Documentation |
|-----------|---------|---------------|
| **ConfigStrategy** | Interface for storing and retrieving configuration data | [Interface docs](interfaces/config-strategy) |
| **ConfigFileLoaderStrategy** | Interface for loading configuration from files | [Interface docs](interfaces/config-file-loader-strategy) |
| **ConfigService** | Coordinates file loading and strategy registration | [Service docs](services/config-service) |
| **ConfigException** | Thrown when configuration operations fail | [Exception docs](exceptions/config-exception) |

---

## Why this package exists

Configuration management seems simple until you need to:

* **Support multiple environments** — Development, staging, production
* **Change file formats** — Move from PHP arrays to JSON or YAML
* **Test configuration** — Mock configuration without file system access
* **Share configuration** — Let packages register their own config sections

Without abstraction, you end up with:

| Problem | What happens |
|---------|--------------|
| Hardcoded file loading | Can't switch from PHP to JSON without rewriting code |
| Global arrays | Hard to test, easy to corrupt, no type safety |
| Scattered `$_ENV` calls | Configuration access spread throughout codebase |
| No namespacing | Package configs collide with application configs |

This package provides **clean interfaces** that separate:

* **What** configuration you need (the keys)
* **Where** it's stored (the strategy)
* **How** it's loaded (the file loader)

---

## Installation

```bash
composer require phpnomad/config
```

**Requirements:** PHP 7.4+

**Dependencies:** None (zero dependencies)

---

## The configuration flow

When loading configuration through `ConfigService`:

```
Config file on disk (PHP, JSON, etc.)
         │
         ▼
┌─────────────────────────────────┐
│  ConfigFileLoaderStrategy       │
│  loadFileConfigs($path)         │
│  → reads file, returns array    │
└─────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────┐
│  ConfigService                  │
│  registerConfig($key, $path)    │
│  → coordinates loading          │
└─────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────┐
│  ConfigStrategy                 │
│  register($key, $data)          │
│  → stores under namespace       │
└─────────────────────────────────┘
         │
         ▼
Application calls $config->get('key.nested.value')
```

Each component has a single responsibility:
* **Loader** — Knows how to read a file format
* **Service** — Orchestrates the process
* **Strategy** — Stores and retrieves data

---

## Quick example

```php
use PHPNomad\Config\Interfaces\ConfigStrategy;
use PHPNomad\Config\Services\ConfigService;

// 1. Create a strategy (in-memory storage)
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

// 2. Register configuration
$config = new ArrayConfig();
$config->register('database', [
    'host' => 'localhost',
    'port' => 3306,
    'credentials' => [
        'username' => 'app_user',
        'password' => 'secret123'
    ]
]);

// 3. Access with dot-notation
$config->get('database.host');                    // 'localhost'
$config->get('database.credentials.username');   // 'app_user'
$config->get('database.timeout', 30);            // 30 (default)
```

---

## When to use this package

The config package is appropriate when:

| Scenario | Why it helps |
|----------|--------------|
| Multi-environment apps | Different strategies for dev/staging/prod |
| Plugin systems | Packages register their own config sections |
| Testing | Mock ConfigStrategy for predictable tests |
| Format flexibility | Switch file formats without code changes |
| Centralized access | One place for all configuration |

### Common use cases

* **Application settings** — Debug mode, timezone, locale
* **Database connections** — Host, port, credentials
* **External services** — API keys, endpoints, timeouts
* **Feature flags** — Enable/disable functionality
* **Cache settings** — Driver, TTL, prefix

---

## When NOT to use this package

### Simple scripts

If you have a single-file script, just use an array:

```php
$config = ['api_key' => 'xxx', 'timeout' => 30];
```

### Environment-only configuration

If you only need environment variables:

```php
$dbHost = getenv('DATABASE_HOST');
```

### No file-based configuration

If all configuration comes from a database or API, you don't need `ConfigFileLoaderStrategy`—just implement `ConfigStrategy` with your storage backend.

---

## Best practices

1. **Use namespaced keys** — Register each domain under a clear namespace
2. **Type your configuration access** — Wrap access in typed methods
3. **Validate configuration early** — Check required keys at bootstrap
4. **Use environment variables for secrets** — Don't hardcode credentials

See the individual component docs for detailed best practices:
- [ConfigStrategy best practices](interfaces/config-strategy#best-practices)
- [ConfigFileLoaderStrategy best practices](interfaces/config-file-loader-strategy#best-practices)
- [ConfigService best practices](services/config-service#best-practices)

---

## Relationship to other packages

### Packages that depend on config

| Package | How it uses config |
|---------|-------------------|
| [json-config-integration](/packages/json-config-integration/introduction) | Provides JSON file loader implementation |
| [wordpress-plugin](/packages/wordpress-plugin/introduction) | Configuration management for WordPress plugins |

### Related packages

| Package | Relationship |
|---------|-------------|
| [di](/packages/di/introduction) | DI container can inject ConfigStrategy |
| [loader](/packages/loader/introduction) | Loader can load configuration modules |

---

## Package contents

### Interfaces

| Interface | Purpose |
|-----------|---------|
| [ConfigStrategy](interfaces/config-strategy) | Configuration storage and retrieval with dot-notation |
| [ConfigFileLoaderStrategy](interfaces/config-file-loader-strategy) | File format loading |

[View all interfaces →](interfaces/introduction)

### Services

| Class | Purpose |
|-------|---------|
| [ConfigService](services/config-service) | Orchestrates file loading and registration |

[View all services →](services/introduction)

### Exceptions

| Class | Purpose |
|-------|---------|
| [ConfigException](exceptions/config-exception) | Configuration operation failures |

---

## Next steps

* **Need JSON config files?** See [JSON Config Integration](/packages/json-config-integration/introduction)
* **Implementing a strategy?** Read [ConfigStrategy interface](interfaces/config-strategy)
* **Loading configuration?** Check [ConfigService](services/config-service)
* **Building a module system?** See [Loader](/packages/loader/introduction) for loading configuration modules
