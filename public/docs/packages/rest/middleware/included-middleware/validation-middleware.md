# ValidationMiddleware

The `ValidationMiddleware` is the bridge between declared **input contracts** and incoming requests.  
It runs before the controller to ensure that required parameters are present, correctly typed, and pass any
custom validation rules you’ve defined.

If any validations fail, it throws a `ValidationException`, which the framework converts into a consistent HTTP error
response.

## Purpose

Controllers should never need to perform defensive checks like “is this field missing?” or “is this string actually an
email?”. That belongs in the validations phase.  

`ValidationMiddleware` ensures:

- Every declared `ValidationSet` is checked against the request.
- Failures are collected into a structured error payload.
- Controllers only run if input passes validation.

This keeps your controller code clean, predictable, and focused on business logic.

## Usage Example

Here’s a controller that requires `name` to be a non-empty string and `age` to be an integer ≥ 18.
It attaches `ValidationMiddleware` with its own validation rules.

```php
<?php

use PHPNomad\Http\Enums\Method;
use PHPNomad\Http\Interfaces\Request;
use PHPNomad\Http\Interfaces\Response;
use PHPNomad\Rest\Interfaces\Controller;
use PHPNomad\Rest\Interfaces\HasValidations;
use PHPNomad\Rest\Interfaces\HasMiddleware;
use PHPNomad\Rest\Middleware\ValidationMiddleware;
use PHPNomad\Rest\Factories\ValidationSet;
use PHPNomad\Rest\Validations\IsType;
use PHPNomad\Rest\Validations\IsGreaterThan;
use PHPNomad\Rest\Enums\BasicTypes;

final class RegisterUser implements Controller, HasMiddleware, HasValidations
{
    public function __construct(private Response $response) {}

    public function getEndpoint(): string
    {
        return '/users/register';
    }

    public function getMethod(): string
    {
        return Method::Post;
    }

    public function getResponse(Request $request): Response
    {
        // At this point, inputs are guaranteed valid.
        $name = (string) $request->getParam('name');
        $age  = (int) $request->getParam('age');

        return $this->response
            ->setStatus(201)
            ->setJson(['message' => "User $name registered."]);
    }

    public function getMiddleware(Request $request): array
    {
        return [
            // Attach validation middleware to enforce rules. This allows you to choose when to validate.
            new ValidationMiddleware($this),
        ];
    }

    public function getValidations(): array
    {
        return [
            'name' => (new ValidationSet())
                ->setRequired()
                ->addValidation(fn() => new IsType(BasicTypes::String)),

            'age' => (new ValidationSet())
                ->setRequired()
                ->addValidation(fn() => new IsType(BasicTypes::Integer))
                ->addValidation(fn() => new IsGreaterThan(17)),
        ];
    }
}
```

### Example request

```
POST /users/register
Content-Type: application/json

{
  "name": "Alice",
  "age": 22
}
```

### Example success response

```json
{
  "message": "User Alice registered."
}
```

### Example failure response

If `age` was `15`:

```json
{
  "error": {
    "message": "Validations failed.",
    "context": {
      "age": [
        "Must be at least 18."
      ]
    }
  }
}
```

---

## Best Practices

* **Always attach ValidationMiddleware** to endpoints that require structured inputs.
* **Combine with type coercion** (e.g., `SetTypeMiddleware`) so values are in the right type before being validated.
* **Keep validations declarative**: don’t bury conditional logic in controllers—express it in `ValidationSet`s.
* **Make error messages user-friendly**: clients should be able to act on them without guesswork.