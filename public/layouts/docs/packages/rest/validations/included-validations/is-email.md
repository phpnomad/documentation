# IsEmail

The `IsEmail` validation ensures that a request parameter is a **validly formatted email address**. It uses PHP’s
built-in functions under the hood, making it a lightweight but reliable validator for user input.

## Usage in a Controller

A typical scenario is validating that a `userEmail` field contains a valid email before processing the request. Here’s
an example controller that creates a user:

```php
<?php

use PHPNomad\Http\Enums\Method;
use PHPNomad\Http\Interfaces\Request;
use PHPNomad\Http\Interfaces\Response;
use PHPNomad\Rest\Interfaces\Controller;
use PHPNomad\Rest\Factories\ValidationSet;
use PHPNomad\Rest\Validations\IsEmail;

final class CreateUser implements Controller
{
    public function __construct(
        private Response $response,
        private UserRepository $users
    ) {}

    public function getEndpoint(): string
    {
        return '/users';
    }

    public function getMethod(): string
    {
        return Method::Post;
    }

    public function getResponse(Request $request): Response
    {
        $userId = $this->users->create(
            email: (string) $request->getParam('userEmail')
        );

        return $this->response
            ->setStatus(201)
            ->setJson(['id' => $userId, 'status' => 'created']);
    }

    public function getValidations(): array
    {
        return [
            'userEmail' => (new ValidationSet())
                ->setRequired()
                ->addValidation(fn() => new IsEmail()),
        ];
    }
}
```

In this example:

* The `userEmail` parameter is required.
* Validation ensures the value is a properly formatted email.
* If the validation fails, the request never reaches the `getResponse` method.

## Error Type

* **Type:** `INVALID_EMAIL`
* **Error Message:**

  ```
  userEmail must be a valid email address.
  ```
* **Context:** empty (this validator doesn’t provide extra context).

## Example Failure Response

If a request is made without a valid `userEmail`, `ValidationMiddleware` throws a `ValidationException`. The system
produces:

```json
{
  "error": {
    "message": "Validations failed.",
    "context": {
      "userEmail": [
        {
          "field": "userEmail",
          "message": "userEmail must be a valid email address.",
          "type": "INVALID_EMAIL"
        }
      ]
    }
  }
}
```