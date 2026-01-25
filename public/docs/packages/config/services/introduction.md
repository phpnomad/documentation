---
id: config-services-introduction
slug: docs/packages/config/services/introduction
title: Config Services Overview
doc_type: explanation
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: Overview of the ConfigService class that orchestrates configuration file loading and registration.
llm_summary: >
  The config package provides one service class: ConfigService. This service orchestrates the workflow
  of loading configuration files via ConfigFileLoaderStrategy and registering them with ConfigStrategy.
  It provides a fluent interface for loading multiple configuration files in sequence.
questions_answered:
  - What services does the config package provide?
  - How does ConfigService work?
  - How do I load multiple configuration files?
audience:
  - developers
  - backend engineers
tags:
  - services
  - config
  - orchestration
llm_tags:
  - service-overview
  - config-service
keywords:
  - ConfigService
  - configuration loading
related:
  - ../introduction
see_also:
  - config-service
  - ../interfaces/config-strategy
  - ../interfaces/config-file-loader-strategy
noindex: false
---

# Config Services

The config package provides one service class that orchestrates configuration loading:

| Service | Purpose |
|---------|---------|
| [ConfigService](config-service) | Coordinates file loading and strategy registration |

---

## The orchestration pattern

`ConfigService` connects the loader and strategy:

```
┌─────────────────────────────────────────────────────────────────┐
│                        ConfigService                            │
│                                                                 │
│   registerConfig('database', '/path/to/database.php')           │
│          │                                                      │
│          ├──→ ConfigFileLoaderStrategy::loadFileConfigs()       │
│          │    (reads file, returns array)                       │
│          │                                                      │
│          └──→ ConfigStrategy::register('database', $data)       │
│               (stores under namespace)                          │
└─────────────────────────────────────────────────────────────────┘
```

---

## Quick example

```php
use PHPNomad\Config\Services\ConfigService;

$strategy = new ArrayConfig();
$loader = new PhpConfigLoader();
$service = new ConfigService($strategy, $loader);

// Load multiple config files
$service
    ->registerConfig('database', __DIR__ . '/config/database.php')
    ->registerConfig('cache', __DIR__ . '/config/cache.php')
    ->registerConfig('mail', __DIR__ . '/config/mail.php');

// Access via strategy
$dbHost = $strategy->get('database.host');
```

---

## Next steps

- [ConfigService](config-service) — Full service documentation
- [ConfigStrategy](../interfaces/config-strategy) — Where configuration is stored
- [ConfigFileLoaderStrategy](../interfaces/config-file-loader-strategy) — How files are loaded
