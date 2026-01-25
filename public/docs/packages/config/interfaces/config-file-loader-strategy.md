---
id: config-interface-config-file-loader-strategy
slug: docs/packages/config/interfaces/config-file-loader-strategy
title: ConfigFileLoaderStrategy Interface
doc_type: reference
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: The ConfigFileLoaderStrategy interface defines how configuration files are loaded and parsed into arrays.
llm_summary: >
  The ConfigFileLoaderStrategy interface enables pluggable file format support for configuration loading.
  It defines a single method loadFileConfigs(string $path): array that reads a file and returns its
  contents as a PHP array. Implementations exist for PHP arrays, JSON, YAML, and other formats.
  Used by ConfigService to load configuration files before registering them with ConfigStrategy.
questions_answered:
  - What is ConfigFileLoaderStrategy?
  - How do I load configuration from files?
  - How do I support custom file formats?
  - How do I implement a YAML config loader?
audience:
  - developers
  - backend engineers
tags:
  - interface
  - config
  - file-loading
llm_tags:
  - config-file-loader
  - file-parsing
  - pluggable-formats
keywords:
  - ConfigFileLoaderStrategy interface
  - configuration file loading
  - file format support
related:
  - ../introduction
  - ../services/config-service
see_also:
  - config-strategy
  - ../exceptions/config-exception
noindex: false
---

# ConfigFileLoaderStrategy Interface

The `ConfigFileLoaderStrategy` interface defines how configuration files are **loaded and parsed**. It enables pluggable file format support.

## Interface definition

```php
namespace PHPNomad\Config\Interfaces;

interface ConfigFileLoaderStrategy
{
    /**
     * Loads configurations from the specified file.
     */
    public function loadFileConfigs(string $path): array;
}
```

## Methods

### `loadFileConfigs(string $path): array`

Reads a configuration file and returns its contents as an array.

**Parameters:**
- `$path` — Absolute path to the configuration file

**Returns:** `array` — The parsed configuration data

**Throws:** `ConfigException` — If file doesn't exist or parsing fails

---

## PHP array loader

The simplest loader—PHP files that return arrays:

```php
use PHPNomad\Config\Interfaces\ConfigFileLoaderStrategy;
use PHPNomad\Config\Exceptions\ConfigException;

class PhpConfigLoader implements ConfigFileLoaderStrategy
{
    public function loadFileConfigs(string $path): array
    {
        if (!file_exists($path)) {
            throw new ConfigException("Config file not found: {$path}");
        }

        $config = require $path;

        if (!is_array($config)) {
            throw new ConfigException("Config file must return an array: {$path}");
        }

        return $config;
    }
}
```

Configuration file:

```php
<?php
// config/database.php
return [
    'driver' => 'mysql',
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'port' => (int)($_ENV['DB_PORT'] ?? 3306),
    'credentials' => [
        'username' => $_ENV['DB_USER'] ?? 'root',
        'password' => $_ENV['DB_PASS'] ?? '',
    ],
];
```

**Advantages of PHP config files:**
- Can include PHP logic and environment variable access
- No parsing overhead
- IDE autocompletion works

---

## JSON loader

```php
class JsonConfigLoader implements ConfigFileLoaderStrategy
{
    public function loadFileConfigs(string $path): array
    {
        if (!file_exists($path)) {
            throw new ConfigException("Config file not found: {$path}");
        }

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

Configuration file:

```json
{
    "driver": "mysql",
    "host": "localhost",
    "port": 3306,
    "credentials": {
        "username": "app_user",
        "password": "secret"
    }
}
```

**Note:** For production JSON config support, see [json-config-integration](/packages/json-config-integration/introduction).

---

## YAML loader

```php
use Symfony\Component\Yaml\Yaml;

class YamlConfigLoader implements ConfigFileLoaderStrategy
{
    public function loadFileConfigs(string $path): array
    {
        if (!file_exists($path)) {
            throw new ConfigException("Config file not found: {$path}");
        }

        try {
            return Yaml::parseFile($path);
        } catch (ParseException $e) {
            throw new ConfigException(
                "Invalid YAML in {$path}: " . $e->getMessage()
            );
        }
    }
}
```

Configuration file:

```yaml
driver: mysql
host: localhost
port: 3306
credentials:
  username: app_user
  password: secret
```

---

## With environment variable expansion

Expand `${VAR}` placeholders in config files:

```php
class EnvExpandingJsonLoader implements ConfigFileLoaderStrategy
{
    public function loadFileConfigs(string $path): array
    {
        if (!file_exists($path)) {
            throw new ConfigException("Config file not found: {$path}");
        }

        $content = file_get_contents($path);

        // Expand ${VAR} and ${VAR:-default} syntax
        $content = preg_replace_callback(
            '/\$\{([A-Z_]+)(?::-([^}]*))?\}/',
            function ($matches) {
                $value = getenv($matches[1]);
                if ($value === false) {
                    return $matches[2] ?? '';
                }
                return $value;
            },
            $content
        );

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

Configuration file:

```json
{
    "host": "${DB_HOST:-localhost}",
    "credentials": {
        "username": "${DB_USER}",
        "password": "${DB_PASS}"
    }
}
```

---

## Composite loader

Support multiple file formats with a single loader:

```php
class CompositeConfigLoader implements ConfigFileLoaderStrategy
{
    private array $loaders = [];

    public function registerLoader(string $extension, ConfigFileLoaderStrategy $loader): void
    {
        $this->loaders[$extension] = $loader;
    }

    public function loadFileConfigs(string $path): array
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        if (!isset($this->loaders[$extension])) {
            throw new ConfigException(
                "No loader registered for .{$extension} files"
            );
        }

        return $this->loaders[$extension]->loadFileConfigs($path);
    }
}

// Usage
$loader = new CompositeConfigLoader();
$loader->registerLoader('php', new PhpConfigLoader());
$loader->registerLoader('json', new JsonConfigLoader());
$loader->registerLoader('yaml', new YamlConfigLoader());

// Automatically uses correct loader based on extension
$loader->loadFileConfigs('/config/database.json');
$loader->loadFileConfigs('/config/cache.yaml');
$loader->loadFileConfigs('/config/app.php');
```

---

## Best practices

### Validate file existence early

```php
public function loadFileConfigs(string $path): array
{
    if (!file_exists($path)) {
        throw new ConfigException("Config file not found: {$path}");
    }

    if (!is_readable($path)) {
        throw new ConfigException("Config file not readable: {$path}");
    }

    // ... load and parse
}
```

### Provide clear error messages

```php
if (json_last_error() !== JSON_ERROR_NONE) {
    throw new ConfigException(sprintf(
        "Failed to parse JSON config file '%s': %s",
        $path,
        json_last_error_msg()
    ));
}
```

### Handle encoding issues

```php
$content = file_get_contents($path);

// Ensure UTF-8
if (!mb_check_encoding($content, 'UTF-8')) {
    $content = mb_convert_encoding($content, 'UTF-8', 'auto');
}
```

---

## Testing

```php
class PhpConfigLoaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/config_test_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir . '/*'));
        rmdir($this->tempDir);
    }

    public function test_loads_php_array_file(): void
    {
        $file = $this->tempDir . '/test.php';
        file_put_contents($file, '<?php return ["key" => "value"];');

        $loader = new PhpConfigLoader();
        $config = $loader->loadFileConfigs($file);

        $this->assertEquals(['key' => 'value'], $config);
    }

    public function test_throws_for_missing_file(): void
    {
        $loader = new PhpConfigLoader();

        $this->expectException(ConfigException::class);
        $loader->loadFileConfigs('/nonexistent/file.php');
    }

    public function test_throws_for_non_array_return(): void
    {
        $file = $this->tempDir . '/test.php';
        file_put_contents($file, '<?php return "not an array";');

        $loader = new PhpConfigLoader();

        $this->expectException(ConfigException::class);
        $loader->loadFileConfigs($file);
    }
}
```

---

## See also

- [ConfigStrategy](config-strategy) — Where loaded configuration is stored
- [ConfigService](../services/config-service) — Orchestrates loading and registration
- [json-config-integration](/packages/json-config-integration/introduction) — Production JSON loader
