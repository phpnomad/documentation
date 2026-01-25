---
id: config-exception-config-exception
slug: docs/packages/config/exceptions/config-exception
title: ConfigException Class
doc_type: reference
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: The ConfigException class is thrown when configuration operations fail.
llm_summary: >
  ConfigException extends the base PHP Exception class and is thrown when configuration operations
  fail. Common scenarios include missing configuration files, invalid file formats, parse errors,
  and missing required configuration keys. Used by ConfigFileLoaderStrategy implementations and
  can be thrown by ConfigStrategy implementations for validation errors.
questions_answered:
  - What is ConfigException?
  - When is ConfigException thrown?
  - How do I handle configuration errors?
  - How do I throw ConfigException?
audience:
  - developers
  - backend engineers
tags:
  - exception
  - config
  - error-handling
llm_tags:
  - config-exception
  - error-handling
keywords:
  - ConfigException class
  - configuration errors
  - exception handling
related:
  - ../introduction
  - ../interfaces/config-file-loader-strategy
see_also:
  - ../services/config-service
  - ../interfaces/config-strategy
noindex: false
---

# ConfigException Class

The `ConfigException` class is thrown when **configuration operations fail**. It extends PHP's base `Exception` class.

## Class definition

```php
namespace PHPNomad\Config\Exceptions;

class ConfigException extends \Exception {}
```

---

## When it's thrown

### Missing configuration file

```php
class PhpConfigLoader implements ConfigFileLoaderStrategy
{
    public function loadFileConfigs(string $path): array
    {
        if (!file_exists($path)) {
            throw new ConfigException("Config file not found: {$path}");
        }
        // ...
    }
}
```

### Invalid file format

```php
class JsonConfigLoader implements ConfigFileLoaderStrategy
{
    public function loadFileConfigs(string $path): array
    {
        $content = file_get_contents($path);
        $config = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ConfigException(
                "Invalid JSON in {$path}: " . json_last_error_msg()
            );
        }

        return $config;
    }
}
```

### Invalid return type

```php
class PhpConfigLoader implements ConfigFileLoaderStrategy
{
    public function loadFileConfigs(string $path): array
    {
        $config = require $path;

        if (!is_array($config)) {
            throw new ConfigException(
                "Config file must return an array: {$path}"
            );
        }

        return $config;
    }
}
```

### Missing required configuration

```php
class ConfigValidator
{
    public function validate(ConfigStrategy $config): void
    {
        $required = ['database.host', 'app.secret_key'];

        foreach ($required as $key) {
            if (!$config->has($key)) {
                throw new ConfigException("Missing required config: {$key}");
            }
        }
    }
}
```

---

## Handling ConfigException

### Basic try-catch

```php
use PHPNomad\Config\Exceptions\ConfigException;

try {
    $service->registerConfig('app', '/path/to/app.php');
} catch (ConfigException $e) {
    error_log("Configuration error: " . $e->getMessage());
    // Handle the error appropriately
}
```

### With fallback configuration

```php
try {
    $service->registerConfig('cache', '/config/cache.php');
} catch (ConfigException $e) {
    // Use default cache configuration
    $strategy->register('cache', [
        'driver' => 'array',
        'ttl' => 3600,
    ]);

    error_log("Using fallback cache config: " . $e->getMessage());
}
```

### Graceful degradation

```php
class Application
{
    public function loadOptionalConfigs(): void
    {
        $optional = ['analytics', 'features', 'experiments'];

        foreach ($optional as $config) {
            try {
                $this->configService->registerConfig(
                    $config,
                    "{$this->configDir}/{$config}.php"
                );
            } catch (ConfigException $e) {
                // Optional configs can fail silently
                $this->logger->debug("Optional config not loaded: {$config}");
            }
        }
    }
}
```

### Fail fast for required configs

```php
class Application
{
    public function loadRequiredConfigs(): void
    {
        $required = ['app', 'database'];

        foreach ($required as $config) {
            try {
                $this->configService->registerConfig(
                    $config,
                    "{$this->configDir}/{$config}.php"
                );
            } catch (ConfigException $e) {
                // Required configs must exist - fail the application
                throw new RuntimeException(
                    "Cannot start application: {$e->getMessage()}",
                    0,
                    $e
                );
            }
        }
    }
}
```

---

## Creating helpful error messages

Include context in exception messages:

```php
// Good: specific and actionable
throw new ConfigException(
    "Config file not found: /app/config/database.php. " .
    "Ensure the file exists and is readable."
);

// Good: includes the parse error details
throw new ConfigException(sprintf(
    "Failed to parse JSON config '%s' at line %d: %s",
    $path,
    $lineNumber,
    $parseError
));

// Bad: vague
throw new ConfigException("Config error");

// Bad: missing file path
throw new ConfigException("File not found");
```

---

## Custom exception subclasses

For complex applications, create specific exception types:

```php
class ConfigFileNotFoundException extends ConfigException
{
    public function __construct(string $path)
    {
        parent::__construct("Config file not found: {$path}");
    }
}

class ConfigParseException extends ConfigException
{
    public function __construct(string $path, string $error)
    {
        parent::__construct("Failed to parse {$path}: {$error}");
    }
}

class MissingConfigKeyException extends ConfigException
{
    public function __construct(string $key)
    {
        parent::__construct("Missing required config key: {$key}");
    }
}

// Usage
try {
    $config = $this->loadConfig($path);
} catch (ConfigFileNotFoundException $e) {
    // Handle missing file specifically
} catch (ConfigParseException $e) {
    // Handle parse errors specifically
} catch (ConfigException $e) {
    // Handle other config errors
}
```

---

## Testing exception scenarios

```php
class ConfigLoaderTest extends TestCase
{
    public function test_throws_for_missing_file(): void
    {
        $loader = new PhpConfigLoader();

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('not found');

        $loader->loadFileConfigs('/nonexistent/file.php');
    }

    public function test_throws_for_invalid_json(): void
    {
        $file = $this->createTempFile('{ invalid json }');
        $loader = new JsonConfigLoader();

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Invalid JSON');

        $loader->loadFileConfigs($file);
    }

    public function test_exception_includes_file_path(): void
    {
        $loader = new PhpConfigLoader();
        $path = '/specific/path/config.php';

        try {
            $loader->loadFileConfigs($path);
            $this->fail('Expected ConfigException');
        } catch (ConfigException $e) {
            $this->assertStringContainsString($path, $e->getMessage());
        }
    }
}
```

---

## See also

- [ConfigFileLoaderStrategy](../interfaces/config-file-loader-strategy) — Where exceptions are typically thrown
- [ConfigService](../services/config-service) — Using the service with error handling
- [ConfigStrategy](../interfaces/config-strategy) — Storage interface that may throw exceptions
