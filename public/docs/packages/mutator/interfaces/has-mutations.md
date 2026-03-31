---
id: mutator-interface-has-mutations
slug: docs/packages/mutator/interfaces/has-mutations
title: HasMutations Interface
doc_type: reference
status: active
language: en
owner: docs-team
last_reviewed: 2026-01-25
applies_to: ["all"]
canonical: true
summary: The HasMutations interface allows objects to advertise their available transformations via getMutations().
llm_summary: >
  The HasMutations interface enables capability discovery by requiring objects to expose their available
  mutations via getMutations(): array. The returned array maps mutation names to their implementations
  (typically callables or handler classes). This makes transformation systems self-documenting and
  enables dynamic UI generation, API documentation, and runtime introspection.
questions_answered:
  - What is HasMutations?
  - How do I expose available transformations?
  - How does capability discovery work?
  - When should I implement HasMutations?
audience:
  - developers
  - backend engineers
tags:
  - interface
  - mutator
  - capability-discovery
llm_tags:
  - has-mutations
  - capability-discovery
  - introspection
keywords:
  - HasMutations interface
  - capability discovery
  - getMutations
related:
  - ../introduction
  - mutation-strategy
see_also:
  - mutator
  - mutator-handler
noindex: false
---

# HasMutations Interface

The `HasMutations` interface enables **capability discovery** by allowing objects to advertise what transformations they support. This makes transformation systems self-documenting.

## Interface definition

```php
namespace PHPNomad\Mutator\Interfaces;

interface HasMutations
{
    public function getMutations(): array;
}
```

## Methods

### `getMutations(): array`

Returns an array of available mutations.

**Parameters:** None

**Returns:** `array` — A map of mutation names to their implementations

**Typical formats:**
- `['name' => callable]` — Name to closure/function
- `['name' => ClassName::class]` — Name to handler class
- `['name' => ['handler' => ..., 'description' => ...]]` — Rich metadata

---

## Why use HasMutations?

| Scenario | How HasMutations helps |
|----------|------------------------|
| Self-documenting APIs | Objects describe what they can do |
| Dynamic UIs | Generate forms/buttons from available mutations |
| Validation | Check if a mutation exists before calling |
| Documentation | Auto-generate docs from mutation lists |
| Plugin systems | Plugins advertise their capabilities |

---

## Basic implementation

```php
use PHPNomad\Mutator\Interfaces\HasMutations;

class TextProcessor implements HasMutations
{
    public function getMutations(): array
    {
        return [
            'uppercase' => fn($text) => strtoupper($text),
            'lowercase' => fn($text) => strtolower($text),
            'reverse'   => fn($text) => strrev($text),
            'wordcount' => fn($text) => str_word_count($text),
        ];
    }

    public function apply(string $mutation, string $text)
    {
        $mutations = $this->getMutations();

        if (!isset($mutations[$mutation])) {
            throw new InvalidArgumentException("Unknown mutation: $mutation");
        }

        return $mutations[$mutation]($text);
    }
}

// Usage
$processor = new TextProcessor();

// Discover available mutations
print_r(array_keys($processor->getMutations()));
// ['uppercase', 'lowercase', 'reverse', 'wordcount']

// Apply a mutation
echo $processor->apply('uppercase', 'hello'); // "HELLO"
```

---

## With metadata

Return rich metadata for documentation or UI generation:

```php
class FormValidator implements HasMutations
{
    public function getMutations(): array
    {
        return [
            'required' => [
                'handler' => fn($v) => !empty($v),
                'description' => 'Validates that the value is not empty',
                'errorMessage' => 'This field is required',
            ],
            'email' => [
                'handler' => fn($v) => filter_var($v, FILTER_VALIDATE_EMAIL) !== false,
                'description' => 'Validates email format',
                'errorMessage' => 'Please enter a valid email address',
            ],
            'minLength' => [
                'handler' => fn($v, $min) => strlen($v) >= $min,
                'description' => 'Validates minimum string length',
                'errorMessage' => 'Must be at least {min} characters',
                'params' => ['min' => 'integer'],
            ],
        ];
    }

    public function validate(string $mutation, $value, ...$params): bool
    {
        $mutations = $this->getMutations();

        if (!isset($mutations[$mutation])) {
            throw new InvalidArgumentException("Unknown validation: $mutation");
        }

        return $mutations[$mutation]['handler']($value, ...$params);
    }

    public function getErrorMessage(string $mutation): string
    {
        return $this->getMutations()[$mutation]['errorMessage'] ?? 'Validation failed';
    }
}
```

---

## With handler classes

Reference handler classes instead of closures:

```php
class DataTransformer implements HasMutations
{
    public function getMutations(): array
    {
        return [
            'slugify' => SlugifyHandler::class,
            'sanitize_html' => HtmlSanitizeHandler::class,
            'format_date' => DateFormatHandler::class,
        ];
    }

    public function apply(string $mutation, ...$args)
    {
        $mutations = $this->getMutations();

        if (!isset($mutations[$mutation])) {
            throw new InvalidArgumentException("Unknown mutation: $mutation");
        }

        $handlerClass = $mutations[$mutation];
        $handler = new $handlerClass();

        return $handler->mutate(...$args);
    }
}
```

---

## Capability checking

Check if a mutation exists before using it:

```php
class SafeProcessor implements HasMutations
{
    // ... getMutations() implementation ...

    public function hasMutation(string $name): bool
    {
        return isset($this->getMutations()[$name]);
    }

    public function apply(string $mutation, $value)
    {
        if (!$this->hasMutation($mutation)) {
            return $value; // Pass through unchanged
        }

        return $this->getMutations()[$mutation]($value);
    }
}

// Safe usage
if ($processor->hasMutation('uppercase')) {
    $result = $processor->apply('uppercase', $input);
}
```

---

## Generating documentation

Use `HasMutations` to auto-generate API documentation:

```php
function generateDocs(HasMutations $object): string
{
    $docs = "Available mutations:\n\n";

    foreach ($object->getMutations() as $name => $mutation) {
        $docs .= "- **{$name}**";

        if (is_array($mutation) && isset($mutation['description'])) {
            $docs .= ": {$mutation['description']}";
        }

        $docs .= "\n";
    }

    return $docs;
}

$processor = new FormValidator();
echo generateDocs($processor);
// Available mutations:
//
// - **required**: Validates that the value is not empty
// - **email**: Validates email format
// - **minLength**: Validates minimum string length
```

---

## Best practices

### Use descriptive mutation names

```php
// Good: descriptive names
return [
    'validate_email' => ...,
    'sanitize_html' => ...,
    'format_currency' => ...,
];

// Bad: vague names
return [
    'validate' => ...,
    'clean' => ...,
    'format' => ...,
];
```

### Keep mutations organized

Group related mutations or use naming conventions:

```php
return [
    // Validation mutations
    'validate.required' => ...,
    'validate.email' => ...,
    'validate.phone' => ...,

    // Transformation mutations
    'transform.uppercase' => ...,
    'transform.slug' => ...,
];
```

### Make getMutations() deterministic

The method should return the same mutations each call:

```php
// Good: always returns same mutations
public function getMutations(): array
{
    return [
        'uppercase' => fn($t) => strtoupper($t),
        'lowercase' => fn($t) => strtolower($t),
    ];
}

// Bad: mutations change based on state
public function getMutations(): array
{
    if ($this->isAdmin) {
        return ['delete' => ...];  // Confusing!
    }
    return [];
}
```

---

## Testing

```php
class TextProcessorTest extends TestCase
{
    public function test_returns_expected_mutations(): void
    {
        $processor = new TextProcessor();

        $mutations = $processor->getMutations();

        $this->assertArrayHasKey('uppercase', $mutations);
        $this->assertArrayHasKey('lowercase', $mutations);
        $this->assertArrayHasKey('reverse', $mutations);
    }

    public function test_mutations_are_callable(): void
    {
        $processor = new TextProcessor();

        foreach ($processor->getMutations() as $name => $mutation) {
            $this->assertTrue(
                is_callable($mutation),
                "Mutation '$name' should be callable"
            );
        }
    }

    public function test_applies_mutation(): void
    {
        $processor = new TextProcessor();

        $result = $processor->apply('uppercase', 'hello');

        $this->assertEquals('HELLO', $result);
    }
}
```

---

## See also

- [MutationStrategy](mutation-strategy) — Register mutations to named actions
- [MutatorHandler](mutator-handler) — Handler interface for mutations
- [Mutator](mutator) — Stateful transformation interface
