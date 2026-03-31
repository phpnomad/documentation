# TranslationStrategy

The `TranslationStrategy` interface is the core contract for all translation operations. It provides two methods that cover the four standard translation operations (simple, with context, plural, plural with context).

## Interface

```php
namespace PHPNomad\Translations\Interfaces;

interface TranslationStrategy
{
    public function translate(string $text, ?string $context = null): string;

    public function translatePlural(string $singular, string $plural, int $count, ?string $context = null): string;
}
```

## translate()

Translates a string, optionally with disambiguation context.

```php
// Simple translation
$translator->translate('Settings');

// With context — helps translators distinguish identical English strings
$translator->translate('Post', 'noun');   // A blog post
$translator->translate('Post', 'verb');   // To post something
```

**Parameters:**

| Parameter | Type | Description |
|---|---|---|
| `$text` | `string` | The source string to translate. Must be a literal string for extraction tools to find it. |
| `$context` | `?string` | Optional disambiguation context. Use when the same English text has multiple meanings. |

**Returns:** The translated string, or the original text if no translation exists.

## translatePlural()

Translates a string with plural form selection.

```php
// Simple plural
$translator->translatePlural('%d item', '%d items', $count);

// Plural with context
$translator->translatePlural('%d Comment', '%d Comments', $count, 'post comments');
```

**Parameters:**

| Parameter | Type | Description |
|---|---|---|
| `$singular` | `string` | The singular form of the string. |
| `$plural` | `string` | The plural form of the string. |
| `$count` | `int` | The number that determines which form to use. |
| `$context` | `?string` | Optional disambiguation context. |

**Returns:** The translated string with the correct plural form for the given count and locale.

## Important: Literal Strings Only

Translation strings **must be literal values** in source code — not variables, constants, or concatenated strings. Extraction tools (`xgettext`, `wp i18n make-pot`) perform static analysis and cannot see runtime values.

```php
// CORRECT — extractors can find these
$translator->translate('Welcome back');
$translator->translatePlural('%d result', '%d results', $count);

// WRONG — extractors cannot see the string
$translator->translate($someVariable);
$translator->translate('Hello ' . $name);
$translator->translate(self::GREETING);
```

If you need dynamic content in translated strings, use `sprintf()` after translation:

```php
$translated = $translator->translate('Welcome back, %s');
$message = sprintf($translated, $userName);
```

## Context Guidelines

Use context when the same English string could be translated differently depending on meaning:

```php
// "Read" can be past tense or imperative
$translator->translate('Read', 'past tense');  // "Lu" in French
$translator->translate('Read', 'imperative');   // "Lire" in French

// "Post" as a thing vs an action
$translator->translate('Post', 'noun');
$translator->translate('Post', 'verb');
```

Context strings are visible to translators in their tools (e.g., Poedit, GlotPress). Write them as short, clear hints — not full sentences.
