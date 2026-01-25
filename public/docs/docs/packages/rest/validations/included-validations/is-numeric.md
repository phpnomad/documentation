# IsNumeric

The `IsNumeric` validation ensures that a request parameter is **numeric**. This includes integers, floats, and numeric
strings (anything PHP’s `is_numeric()` would accept).

This validation is especially useful for parameters that arrive as strings via HTTP but need to represent numbers, such
as IDs, quantities, or counts.

## Usage in a Controller

Below, the endpoint accepts a `count` parameter that must be numeric. The controller declares this rule in
a `ValidationSet`, and `ValidationMiddleware` enforces it before the handler runs.

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
use PHPNomad\Rest\Validations\IsNumeric;

final class GenerateReport implements Controller, HasValidations, HasMiddleware
{
    public function __construct(private Response $response, private ReportService $reports) {}

    public function getEndpoint(): string { return '/reports/generate'; }
    public function getMethod(): string   { return Method::Post; }

    public function getResponse(Request $request): Response
    {
        // At this point, "count" is guaranteed numeric.
        $count = (int) $request->getParam('count');

        $report = $this->reports->generate($count);

        return $this->response
            ->setStatus(200)
            ->setJson([
                'count'  => $count,
                'report' => $report
            ]);
    }

    // Declare validations for this endpoint.
    public function getValidations(): array
    {
        return [
            'count' => (new ValidationSet())
                ->setRequired()
                ->addValidation(fn() => new IsNumeric()),
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
POST /reports/generate
Content-Type: application/json

{ "count": "10" }
```

```json
{
  "count": 10,
  "report": {
    /* report contents */
  }
}
```

### Example (failure)

When `count` is not numeric:

```
POST /reports/generate
Content-Type: application/json

{ "count": "ten" }
```

```json
{
  "error": {
    "message": "Validations failed.",
    "context": {
      "count": [
        {
          "field": "count",
          "message": "The key count must be numeric.",
          "type": "REQUIRES_NUMERIC",
          "context": {}
        }
      ]
    }
  }
}
```

## Notes

* `IsNumeric` does **not** coerce values — it only checks. Use it alongside `SetTypeMiddleware` if you want to ensure
  the parameter is converted to an integer or float before controller code.
* For stricter cases (e.g., only integers allowed), combine `IsNumeric` with an `IsType(Integer)` validation.
* This validation pairs well with others, like `IsGreaterThan`, to enforce both type and range.