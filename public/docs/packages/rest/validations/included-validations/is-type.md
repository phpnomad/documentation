# IsType

The `IsType` validation ensures that a request parameter matches a specific **basic type**.
It supports the built-in `BasicTypes` enumeration, covering common cases
like `Integer`, `Float`, `Boolean`, `String`, `Array`, `Object`, and `Null`.

This validation is particularly useful for APIs that need to guarantee type safety before controller logic executes.

## Class Definition

```php
namespace PHPNomad\Rest\Validations;

use PHPNomad\Rest\Interfaces\Validation;
use PHPNomad\Rest\Traits\WithProvidedErrorMessage;
use PHPNomad\Http\Interfaces\Request;
use PHPNomad\Rest\Enums\BasicTypes;

class IsType implements Validation
```

## Usage in a Controller

Here’s an example endpoint that requires an integer `userId` and a boolean `isActive` flag. Both fields are validated
before the controller runs:

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
use PHPNomad\Rest\Middleware\SetTypeMiddleware;
use PHPNomad\Rest\Validations\IsType;
use PHPNomad\Rest\Enums\BasicTypes;

final class UpdateUserStatus implements Controller, HasValidations, HasMiddleware
{
    public function __construct(private Response $response, private UserService $users) {}

    public function getEndpoint(): string { return '/users/{userId}/status'; }
    public function getMethod(): string   { return Method::Post; }

    public function getResponse(Request $request): Response
    {
        // At this point, inputs passed validation.
        // userId was coerced to int by middleware, isActive validated strictly and we cast now.
        $id       = (int)  $request->getParam('userId');
        $isActive = (bool) $request->getParam('isActive');

        $this->users->setActiveStatus($id, $isActive);

        return $this->response
            ->setStatus(200)
            ->setJson(['id' => $id, 'isActive' => $isActive]);
    }

    /** Declare validations for each field */
    public function getValidations(): array
    {
        return [
            'userId' => (new ValidationSet())
                ->setRequired()
                ->addValidation(fn() => new IsType(BasicTypes::Integer)),

            // IMPORTANT: Do not coerce first; let IsType(Boolean) validate the literal input.
            'isActive' => (new ValidationSet())
                ->setRequired()
                ->addValidation(fn() => new IsType(BasicTypes::Boolean)),
        ];
    }

    /** Compose middleware: coerce userId to int, then run validations */
    public function getMiddleware(Request $request): array
    {
        return [
            new SetTypeMiddleware('userId', BasicTypes::Integer),
            // Validation must come after any coercion middleware.
            new ValidationMiddleware($this),
        ];
    }
}
```

## Error Type and Context

* **Type:** `INVALID_TYPE`
* **Error Message:**

  ```
  userId must be an Integer, was given abc
  ```
* **Context:**

  ```json
  {
    "requiredType": "Integer"
  }
  ```

## Example Failure Response

If the client passes `"userId": "abc"` and `"isActive": "yes"`, the response might look like this:

```json
{
  "error": {
    "message": "Validations failed.",
    "context": {
      "userId": [
        {
          "field": "userId",
          "message": "userId must be a Integer, was given abc",
          "type": "INVALID_TYPE",
          "context": {
            "requiredType": "Integer"
          }
        }
      ],
      "isActive": [
        {
          "field": "isActive",
          "message": "isActive must be a Boolean, was given yes",
          "type": "INVALID_TYPE",
          "context": {
            "requiredType": "Boolean"
          }
        }
      ]
    }
  }
}
```

Absolutely — great idea to show **type coercion + validation together**. One nuance: coercing **booleans**
with `settype()` can accidentally turn any non-empty string into `true`. So in the example below we **coerce the integer
** (`userId`) with `SetTypeMiddleware`, but we **do not** coerce the boolean (`isActive`) before validation; we
let `IsType(Boolean)` validate strings like `"true"`, `"false"`, `"1"`, `"0"` strictly.

Here’s an updated section you can drop into the **`IsType`** docs.

---

## Using `IsType` with Middleware (coercion + validation)

Below, the endpoint requires:

* `userId`: an **integer** (coerced by middleware, then validated)
* `isActive`: a **boolean** (validated strictly, then cast in the controller)

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
use PHPNomad\Rest\Middleware\SetTypeMiddleware;
use PHPNomad\Rest\Validations\IsType;
use PHPNomad\Rest\Enums\BasicTypes;

final class UpdateUserStatus implements Controller, HasValidations, HasMiddleware
{
    public function __construct(private Response $response, private UserService $users) {}

    public function getEndpoint(): string { return '/users/{userId}/status'; }
    public function getMethod(): string   { return Method::Post; }

    public function getResponse(Request $request): Response
    {
        // At this point, inputs passed validation.
        // userId was coerced to int by middleware, isActive validated strictly and we cast now.
        $id       = (int)  $request->getParam('userId');
        $isActive = (bool) $request->getParam('isActive');

        $this->users->setActiveStatus($id, $isActive);

        return $this->response
            ->setStatus(200)
            ->setJson(['id' => $id, 'isActive' => $isActive]);
    }

    /** Declare validations for each field */
    public function getValidations(): array
    {
        return [
            'userId' => (new ValidationSet())
                ->setRequired()
                ->addValidation(fn() => new IsType(BasicTypes::Integer)),

            // IMPORTANT: Do not coerce first; let IsType(Boolean) validate the literal input.
            'isActive' => (new ValidationSet())
                ->setRequired()
                ->addValidation(fn() => new IsType(BasicTypes::Boolean)),
        ];
    }

    /** Compose middleware: coerce userId to int, then run validations */
    public function getMiddleware(Request $request): array
    {
        return [
            new SetTypeMiddleware('userId', BasicTypes::Integer),
            new ValidationMiddleware($this),
        ];
    }
}
```

* `userId`: coercion is safe (`"42"` → `42`) and avoids repeating casts in your controller.
* `isActive`: coercing **before** validating would turn *any* non-empty string into `true`. By **validating first**
  with `IsType(Boolean)`, values like `"true"`, `"false"`, `"1"`, `"0"`, `"yes"`, `"no"` are accepted; nonsense
  strings (e.g., `"perhaps"`) are rejected. After validation passes, casting to `(bool)` in the controller is safe and
  intentional.

### Example request (success)

```
POST /users/42/status
Content-Type: application/json

{ "isActive": "true" }
```

```json
{
  "id": 42,
  "isActive": true
}
```

### Example request (failure)

```
POST /users/42/status
Content-Type: application/json

{ "isActive": "perhaps" }
```

```json
{
  "error": {
    "message": "Validations failed.",
    "context": {
      "isActive": [
        {
          "field": "isActive",
          "message": "isActive must be a Boolean, was given perhaps",
          "type": "INVALID_TYPE",
          "context": {
            "requiredType": "Boolean"
          }
        }
      ]
    }
  }
}
```
