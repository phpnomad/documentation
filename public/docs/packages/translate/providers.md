# Providers

The translate package includes provider interfaces that supply domain and locale information to translation strategy implementations.

## HasTextDomain

Provides the text domain (translation namespace) for the current application or plugin.

```php
namespace PHPNomad\Translations\Interfaces;

interface HasTextDomain
{
    public function getTextDomain(): string;
}
```

Text domains prevent translation collisions between different packages. For example, a WordPress plugin uses its slug as the domain (`'my-plugin'`), and each translation file is scoped to that domain.

**Usage:** Implementations of `TranslationStrategy` receive a `HasTextDomain` provider via constructor injection and use it to resolve the domain for every translation call. Application code does not interact with this interface directly.

**Example implementation:**

```php
class PluginConfigProvider implements HasTextDomain
{
    public function getTextDomain(): string
    {
        return 'my-plugin';
    }
}
```

## HasLanguage

Provides the current locale/language for translation.

```php
namespace PHPNomad\Translations\Interfaces;

interface HasLanguage
{
    public function getLanguage(): ?string;
}
```

Returns a locale string (e.g., `'en'`, `'fr_FR'`) or `null` if no specific locale is set (use the platform default).

**Usage:** Non-WordPress strategy implementations use this to determine which language to translate into. The WordPress implementation does not use this interface — WordPress manages locale through its own globals.

## HasDefaultLanguage

Extends `HasLanguage` with a guaranteed non-null return.

```php
namespace PHPNomad\Translations\Interfaces;

interface HasDefaultLanguage extends HasLanguage
{
    public function getLanguage(): string;
}
```

Use this when a locale is always required (e.g., a SaaS application that defaults to `'en'`).

## HeaderLanguageProvider

A built-in implementation of `HasLanguage` that reads the locale from the HTTP `Accept-Language` header.

```php
use PHPNomad\Translations\Providers\HeaderLanguageProvider;

// Returns 'en' for Accept-Language: en-US,en;q=0.9,fr;q=0.8
// Returns null if the header is not present
$provider = new HeaderLanguageProvider();
$locale = $provider->getLanguage(); // 'en' or null
```

This is useful for SaaS applications and APIs where the client specifies their preferred language via HTTP headers.
