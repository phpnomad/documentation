---
id: config-interfaces-introduction
slug: docs/packages/config/interfaces/introduction
title: Config Interfaces Overview
doc_type: explanation
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: Overview of the two interfaces provided by the config package for configuration management.
llm_summary: >
  The config package provides two interfaces: ConfigStrategy for storing and retrieving configuration
  data with dot-notation access, and ConfigFileLoaderStrategy for loading configuration from files.
  ConfigStrategy is the main interface applications interact with, while ConfigFileLoaderStrategy
  enables pluggable file format support (PHP arrays, JSON, YAML, etc.).
questions_answered:
  - What interfaces does the config package provide?
  - How do the config interfaces relate to each other?
  - Which interface should I implement?
audience:
  - developers
  - backend engineers
tags:
  - interfaces
  - config
  - configuration
llm_tags:
  - interface-overview
  - config-interfaces
keywords:
  - config interfaces
  - ConfigStrategy
  - ConfigFileLoaderStrategy
related:
  - ../introduction
see_also:
  - config-strategy
  - config-file-loader-strategy
  - ../services/config-service
noindex: false
---

# Config Interfaces

The config package provides two interfaces that separate configuration storage from file loading:

| Interface | Purpose | When to Implement |
|-----------|---------|-------------------|
| [ConfigStrategy](config-strategy) | Store and retrieve configuration with dot-notation | Custom storage backends (database, Redis, etc.) |
| [ConfigFileLoaderStrategy](config-file-loader-strategy) | Load configuration from files | Custom file formats (YAML, TOML, etc.) |

---

## How the interfaces relate

```
┌──────────────────────────────────┐
│         Config Files             │
│   (PHP, JSON, YAML, etc.)        │
└─────────────┬────────────────────┘
              │
              ▼
┌──────────────────────────────────┐
│    ConfigFileLoaderStrategy      │
│    loadFileConfigs($path)        │
│    → reads file, returns array   │
└─────────────┬────────────────────┘
              │
              ▼
┌──────────────────────────────────┐
│        ConfigService             │
│    (orchestrates the flow)       │
└─────────────┬────────────────────┘
              │
              ▼
┌──────────────────────────────────┐
│       ConfigStrategy             │
│    register($key, $data)         │
│    get($key) / has($key)         │
└──────────────────────────────────┘
              │
              ▼
         Application
```

---

## Choosing what to implement

**Implement ConfigStrategy** when you need:
- A custom storage backend (database, Redis, environment variables)
- Caching or lazy-loading behavior
- Validation on configuration access

**Implement ConfigFileLoaderStrategy** when you need:
- Support for a new file format (YAML, TOML, INI)
- Custom parsing logic (environment variable expansion, etc.)
- Encrypted configuration files

**Use existing implementations** when:
- In-memory storage works for your needs
- JSON or PHP array files are sufficient

---

## Next steps

- [ConfigStrategy](config-strategy) — Configuration storage and retrieval
- [ConfigFileLoaderStrategy](config-file-loader-strategy) — File format loading
- [ConfigService](../services/config-service) — Orchestration service
