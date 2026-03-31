# Translation

The `phpnomad/translate` package provides a **platform-agnostic translation interface** for internationalization (i18n). It defines how translatable strings are resolved at runtime, while leaving the details of locale management, text domains, and catalogue storage to platform-specific implementations.

## Design Philosophy

Translation in PHPNomad follows the same strategy pattern used throughout the framework. The `TranslationStrategy` interface defines *what* your code needs (translate a string, handle plurals), while implementations decide *how* (WordPress gettext, Symfony Translator, native PHP gettext, etc.).

Two key concerns are deliberately separated from the call site:

* **Text domain** — resolved by the implementation from an injected `HasTextDomain` provider. Most call sites share a single domain; extensions that need a different domain get their own strategy instance.
* **Locale** — resolved by the platform. WordPress uses its own globals. Symfony passes locale to its translator. Native gettext uses `setlocale()`. The caller doesn't know or care.

This means 95% of translation calls are clean and simple — just the string and optionally a context.

## Installation

```bash
composer require phpnomad/translate
```

You also need a platform-specific implementation:

| Package | Platform |
|---|---|
| `phpnomad/wordpress-integration` | WordPress (wraps `__()`, `_x()`, `_n()`, `_nx()`) |
| `phpnomad/symfony-translation-integration` | Symfony Translator |
| `phpnomad/gettext-integration` | Native PHP gettext extension |

## Quick Start

Inject `TranslationStrategy` into any class that needs to translate strings:

```php
use PHPNomad\Translations\Interfaces\TranslationStrategy;

class MyService
{
    protected TranslationStrategy $translator;

    public function __construct(TranslationStrategy $translator)
    {
        $this->translator = $translator;
    }

    public function getGreeting(): string
    {
        return $this->translator->translate('Hello, world!');
    }
}
```

The container resolves the correct implementation based on your platform's initializer bindings.
