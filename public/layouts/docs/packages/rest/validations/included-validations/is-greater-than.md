# IsGreaterThan

The `IsGreaterThan` validation ensures that a request parameter is **strictly greater than a specified numeric value**.
This is useful for enforcing minimum thresholds, such as “age must be greater than 18” or “quantity must be greater than
0.”


## Usage in a Controller

Below, the endpoint accepts an `age` parameter that must be greater than **18**. The controller declares this rule
using `ValidationSet`, and `ValidationMiddleware` enforces it before the handler runs.

```php
<?php

use PHPNomad\Http\Enums\Method;
use PHPNomad\Http\Interfaces\Request;
use PHPNomad\Http\Interfaces\Response;

use PHPNomad\Rest\Interfaces\Controller;
use PHPNomad\Rest\Interfaces\HasValidations;
use PHPNomad\Rest\Interfaces\HasMiddleware;

use PHPNomad\Rest\Factories\ValidationSet;
use PHPNomad\Rest\Middleware\ValidationMiddleware;
use PHPNomad\Rest\Validations\IsGreaterThan;

final class RegisterAdult implements Controller, HasValidations, HasMiddleware
{
    public function __construct(private Response $response) {}

    public function getEndpoint(): string { return '/adults/register'; }
    public function getMethod(): string   { return Method::Post; }

    public function getResponse(Request $request): Response
    {
        // At this point, "age" is guaranteed to be > 18.
        return $this->response
            ->setStatus(201)
            ->setJson([
                'name' => $request->getParam('name'),
                'age'  => $request->getParam('age'),
                'status' => 'registered'
            ]);
    }

    // Declare validations for this endpoint.
    public function getValidations(): array
    {
        return [
            'age' => (new ValidationSet())
                ->setRequired()
                ->addValidation(fn() => new IsGreaterThan(17)),
        ];
    }

    // Attach the middleware that executes validations.
    public function getMiddleware(Request $request): array
    {
        return [ new ValidationMiddleware($this) ];
    }
}
```

### Example (success)

```
POST /adults/register
Content-Type: application/json

{ "name": "Alice", "age": 25 }
```

```json
{
  "name": "Alice",
  "age": 25,
  "status": "registered"
}
```

### Example (failure)

When `age` is `15`, the middleware throws a `ValidationException` and the framework returns:

```json
{
  "error": {
    "message": "Validations failed.",
    "context": {
      "age": [
        {
          "field": "age",
          "message": "age must be greater than 18. Was given 15",
          "type": "VALUE_TOO_SMALL",
          "context": {
            "minimumValue": 18
          }
        }
      ]
    }
  }
}
```

## Notes

* If the field is **missing**, the default error message clarifies that a value was expected:
  `"age must be greater than 18, but no value was given."`
* You can compose `IsGreaterThan` with other validations in the same `ValidationSet` (e.g., `IsType(Integer)`) for
  stricter guarantees.
* The `context` always includes the required minimum value so clients can adjust their input programmatically.