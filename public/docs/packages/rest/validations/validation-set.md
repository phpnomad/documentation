# ValidationSet

A `ValidationSet` is the way you declare validations for a single field in PHPNomad.  
It acts as a container for one or more validation rules and knows whether the field is required.

When `ValidationMiddleware` runs, it asks each `ValidationSet` to evaluate the incoming request and collect any
failures. This keeps validations **declarative and composable**: controllers don’t run checks inline, they simply return
an array of validation sets.

## Purpose

Instead of scattering `if` statements around your controller code, a `ValidationSet` lets you declare:

- Whether the field is **required**.
- What rules must be applied to the field (via `addValidation`).
- How failures should be collected and described.

This makes the input contract explicit, testable, and reusable.

## API

### `addValidation(Closure $validationGetter)`

Adds a validation to the set.  
The closure should return a `Validation` instance when called.

```php
$set = (new ValidationSet())
    ->addValidation(fn() => new IsInteger())
    ->addValidation(fn() => new MinValue(1));
````

### `setRequired(bool $isRequired = true)`

Marks the field as required.
If the field is missing, the set automatically produces a “required” failure, without needing a separate validation.

```php
$set = (new ValidationSet())
    ->setRequired()
    ->addValidation(fn() => new IsString());
```

## Example

Here’s how you might declare validations for a `username` field in a controller:

```php
use PHPNomad\Rest\Factories\ValidationSet;
use App\Validations\IsNotReservedUsername;

public function getValidations(): array
{
    return [
        'username' => (new ValidationSet())
            ->setRequired()
            ->addValidation(fn() => new IsString())
            ->addValidation(fn() => new MinLength(3))
            ->addValidation(fn() => new IsNotReservedUsername()),
    ];
}
```

If the request payload is missing `username`, the set will automatically produce a “required” failure.
If it’s present but invalid, all failing rules will be collected and returned.

## Example failure response

When `ValidationMiddleware` runs and finds validation failures, it throws a `ValidationException`. The framework catches
this and generates a structured error response. Errors are grouped by field, with each failure including a message,
type, and context.

This means that clients can see all problems at once and handle them intelligently.

```json
{
  "error": {
    "message": "Validations failed.",
    "context": {
      "username": [
        {
          "field": "username",
          "message": "username is required.",
          "type": "REQUIRED"
        }
      ],
      "email": [
        {
          "field": "email",
          "message": "Must be a valid email address.",
          "type": "invalid_email",
          "context": {
            "value": "not-an-email"
          }
        }
      ],
      "password": [
        {
          "field": "password",
          "message": "Must be at least 12 characters.",
          "type": "min_length",
          "context": {
            "min": 12,
            "actual": 7
          }
        },
        {
          "field": "password",
          "message": "Must include at least one number.",
          "type": "pattern_missing_digit"
        }
      ]
    }
  }
}
```

## Best Practices

* **Always use `setRequired()` for required fields** — don’t reinvent the “is required” check in a custom validation.
* **Compose multiple rules** in a single set; don’t build monolithic validators.
* **Favor closures over instances** when adding validations — they’re cached automatically and won’t bloat memory.
* **Keep error messages clear** — client developers should be able to act on them without guesswork.