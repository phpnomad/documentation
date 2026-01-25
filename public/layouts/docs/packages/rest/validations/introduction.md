# Validations

Validations in PHPNomad are designed to make **input expectations explicit and declarative**. Instead of scattering
checks throughout your controller code, you can attach a set of rules that describe what makes a request valid. This
makes endpoints easier to reason about and more portable across different contexts.

## Declarative by Design

A validation is a small class that implements the `Validation` interface. It defines three things:

* **What to check** — via `isValid()`, given the current request.
* **What message to return** — via `getErrorMessage()`.
* **How to describe the failure** — via `getType()` and `getContext()` for machine-readable error handling.

This means you can build reusable validation rules like “IDs must exist,” “this field must be unique,” or “value must
match a regex,” and apply them consistently wherever needed.

## How Validations Run

You don’t call validations directly. Instead, PHPNomad provides the `ValidationMiddleware`, a built-in middleware that
automatically runs the validations you’ve declared for a controller or another provider.

This middleware iterates over each field’s [Validation Set](/packages/rest/validations/validation-set), checking if the field is required and
applying each validation rule in turn.

If any rules fail, the middleware throws a `ValidationException`, and the system generates a structured error response.
This keeps controllers focused on business logic, while still ensuring strong input guarantees.

## Why It Matters

By keeping validations declarative:

* **Controllers stay clean** — no inline `if` checks scattered around.
* **Errors are consistent** — all validation failures return the same error format.
* **Rules are reusable** — you can apply the same validation logic across multiple endpoints.

## Example: Custom Validation with a Datastore

Suppose you want to ensure that a record **does not already exist** before creating it. For example, you might prevent
creating a user with an `id` that’s already taken. You can implement this as a reusable validation that queries a
datastore.

```php
<?php

namespace App\Validations;

use PHPNomad\Rest\Interfaces\Validation;
use PHPNomad\Http\Interfaces\Request;
use PHPNomad\Datastore\Exceptions\RecordNotFound;
use PHPNomad\Datastore\Interfaces\Datastore;

final class DoesNotExist implements Validation
{
    public function __construct(private Datastore $datastore) {}

    public function isValid(string $key, Request $request): bool
    {
        $id = $request->getParam($key);

        try {
            $this->datastore->find($id);
            // If a record is found, this is a failure.
            return false;
        } catch (RecordNotFound $e) {
            // Not found means it’s valid.
            return true;
        }
    }

    public function getErrorMessage(): string
    {
        return 'A record with this identifier already exists.';
    }

    public function getType(): string
    {
        return 'record_exists';
    }

    public function getContext(string $key, Request $request): array
    {
        return [
            'field' => $key,
            'value' => $request->getParam($key),
        ];
    }
}
```

This validation:

* Looks up the parameter value in a datastore.
* If the record exists, the validation fails.
* If the datastore throws `RecordNotFound`, the validation passes.
* Produces a structured error message and type when it fails.

---

## Attaching the Validation

Here’s how a controller might use it when creating a new record:

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
use App\Validations\DoesNotExist;
use PHPNomad\Datastore\Interfaces\Datastore;

final class CreateUser implements Controller, HasValidations, HasMiddleware
{
    public function __construct(
        private Response $response,
        private Datastore $users
    ) {}

    public function getEndpoint(): string { return '/users'; }
    public function getMethod(): string   { return Method::Post; }

    public function getResponse(Request $request): Response
    {
        // At this point, we know "id" does not already exist.
        $id = $request->getParam('id');

        $this->users->create(['id' => $id]);

        return $this->response
            ->setStatus(201)
            ->setJson(['id' => $id, 'status' => 'created']);
    }

    public function getValidations(): array
    {
        return [
            'id' => (new ValidationSet())
                ->setRequired()
                ->addValidation(fn() => new DoesNotExist($this->users)),
        ];
    }

    public function getMiddleware(Request $request): array
    {
        return [ new ValidationMiddleware($this) ];
    }
}
```

---

### Example request

```
POST /users
Content-Type: application/json

{
  "id": "abc123"
}
```

### Example error response (if a record with `id=abc123` already exists)

```json
{
  "error": {
    "message": "Validations failed.",
    "context": {
      "id": [
        "A record with this identifier already exists."
      ]
    }
  }
}
```