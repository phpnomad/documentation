# IsAny

The `IsAny` validation ensures that a request parameter matches **one of a predefined list of acceptable values**. This
is particularly useful when restricting input to an enumeration of allowed options.

## Usage

```php
use PHPNomad\Rest\Validations\IsAny;

$validation = new IsAny(['draft', 'published', 'archived']);
```

The above will only pass validation if the request parameter matches **exactly one** of `draft`, `published`,
or `archived`.

## Constructor

```php
public function __construct($validItems, $errorMessage = null)
```

* **`$validItems`** (`array`) – A list of allowed values.
* **`$errorMessage`** (`string|callable|null`) – An optional custom error message. If omitted, a default error message
  is generated automatically.

## Using IsAny in a Controller

Below, the endpoint accepts a `status` parameter that must be one of
`draft`, `published`, or `archived`. The controller declares this rule
declaratively; `ValidationMiddleware` runs it before the handler.

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
use PHPNomad\Rest\Validations\IsAny;

final class UpdatePostStatus implements Controller, HasValidations, HasMiddleware
{
    public function __construct(private Response $response, private PostService $posts) {}

    public function getEndpoint(): string { return '/posts/{id}/status'; }
    public function getMethod(): string   { return Method::Post; }

    public function getResponse(Request $request): Response
    {
        // At this point, "status" is guaranteed to be within the allowed set.
        $id     = (int) $request->getParam('id');
        $status = (string) $request->getParam('status');

        $this->posts->setStatus($id, $status);

        return $this->response
            ->setStatus(200)
            ->setJson(['id' => $id, 'status' => $status]);
    }

    // Declare validations for this endpoint.
    public function getValidations(): array
    {
        return [
            'status' => (new ValidationSet())
                ->setRequired()
                ->addValidation(fn() => new IsAny(['draft', 'published', 'archived'])),
        ];
    }

    // Attach the middleware that executes validations.
    public function getMiddleware(Request $request): array
    {
        return [ new ValidationMiddleware($this) ];
    }
}
````

### Example (success)

```
POST /posts/42/status
Content-Type: application/json

{ "status": "published" }
```

```json
{
  "id": 42,
  "status": "published"
}
```

### Example (failure)

When `status` is missing **or** not in the allowed set, the middleware throws a `ValidationException` and the framework
returns a structured error:

```json
{
  "error": {
    "message": "Validations failed.",
    "context": {
      "status": [
        {
          "field": "status",
          "message": "status must be draft, published, or archived, but was given deleted",
          "type": "REQUIRES_ANY",
          "context": {
            "validValues": [
              "draft",
              "published",
              "archived"
            ]
          }
        }
      ]
    }
  }
}
```

### Optional: custom message

`IsAny` accepts an optional custom message (string or callable). For example:

```php
'status' => (new ValidationSet())
    ->setRequired()
    ->addValidation(fn() => new IsAny(
        ['draft', 'published', 'archived'],
        fn (string $key, Request $req) => sprintf(
            'Invalid %s: must be %s.',
            $key,
            implode(', ', ['draft', 'published', 'archived'])
        )
    )),
```

## Behavior

* Calls `$request->getParam($key)` and checks if the value exists in `$validItems`.
* If the value is missing or not in the list, validation fails.
* Returns a contextual error message, e.g.:

```
status must be draft, published, or archived, but was given deleted
```

If no value was provided:

```
status must be draft, published, or archived, but no value was given
```

## Error Type

* **Type:** `REQUIRES_ANY`
* **Context:**

  ```json
  {
    "validValues": ["draft", "published", "archived"]
  }
  ```

## Example Failure Response

For a missing or invalid `status`, the system may produce:

```json
{
  "error": {
    "message": "Validations failed.",
    "context": {
      "status": [
        {
          "field": "status",
          "message": "status must be draft, published, or archived, but was given deleted",
          "type": "REQUIRES_ANY",
          "context": {
            "validValues": [
              "draft",
              "published",
              "archived"
            ]
          }
        }
      ]
    }
  }
}
```