# KeysAreAny

The `KeysAreAny` validation ensures that **all keys of an input array parameter** are part of a predefined set of
allowed values.

This is useful for cases where clients may send dynamic filters, attributes, or options, but you only want to allow a
specific whitelist of keys.

## Usage in a Controller

Below, the endpoint accepts a `filters` parameter, which must be an object (associative array) where the **keys** are
limited to `status` and `category`.

If the request contains any other keys, validation fails.

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
use PHPNomad\Rest\Validations\KeysAreAny;

final class SearchPosts implements Controller, HasValidations, HasMiddleware
{
    public function __construct(private Response $response, private PostService $posts) {}

    public function getEndpoint(): string { return '/posts/search'; }
    public function getMethod(): string   { return Method::Post; }

    public function getResponse(Request $request): Response
    {
        // At this point, "filters" is guaranteed to only contain valid keys.
        $filters = (array) $request->getParam('filters');

        $results = $this->posts->search($filters);

        return $this->response
            ->setStatus(200)
            ->setJson(['results' => $results]);
    }

    public function getValidations(): array
    {
        return [
            'filters' => (new ValidationSet())
                ->setRequired()
                ->addValidation(fn() => new KeysAreAny(['status', 'category'])),
        ];
    }

    public function getMiddleware(Request $request): array
    {
        return [ new ValidationMiddleware($this) ];
    }
}
```

---

### Example (success)

```
POST /posts/search
Content-Type: application/json

{
  "filters": {
    "status": "published",
    "category": "tech"
  }
}
```

```json
{
  "results": [
    {
      "id": 1,
      "title": "Scaling PHPNomad APIs",
      "status": "published",
      "category": "tech"
    }
  ]
}
```

### Example (failure)

When the client sends a `filters` object with disallowed keys (e.g., `author`), the middleware throws
a `ValidationException`:

```json
{
  "error": {
    "message": "Validations failed.",
    "context": {
      "filters": [
        {
          "field": "filters",
          "message": "keys for filters must be status or category, but was given status,author",
          "type": "REQUIRES_ANY",
          "context": {
            "validValues": [
              "status",
              "category"
            ]
          }
        }
      ]
    }
  }
}
```

## Notes

* **Checks keys only**: The values of the array are not validated here — use other validations for that.
* **Good for filters/attributes**: Useful when accepting flexible filter or metadata objects from clients.
* **Composability**: Combine `KeysAreAny` with other validations like `IsType(Array)` or custom rules to validate both
  structure and content.
* **Custom error message**: You can override the default message by providing a string or callable to the constructor.