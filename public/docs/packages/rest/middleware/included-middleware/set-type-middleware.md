# SetTypeMiddleware

The `SetTypeMiddleware` is a built-in middleware in PHPNomad for **type coercion**.  
It ensures that request parameters have the expected PHP type before the controller sees them, so business logic can
trust values are already in the right form.

HTTP parameters arrive as strings by default, regardless of whether they represent numbers, booleans, or arrays.  
`SetTypeMiddleware` lets you declare that a specific parameter should always be treated as a certain type. For example,
if your endpoint expects a `float price` or an `int userId`, you can coerce it automatically rather than repeating
casts inside your controllers.

## Usage Example

Suppose you have an endpoint that accepts a `price` parameter, which should always be a float.
By attaching `SetTypeMiddleware`, you guarantee that `$request->getParam('price')` is already a float when the
controller runs.

## Usage Example

Suppose you have an endpoint that accepts a `userId` parameter, which should always be treated as an integer.  
By attaching `SetTypeMiddleware`, you guarantee that `$request->getParam('userId')` is already an integer when the
controller runs.

```php
<?php

use PHPNomad\Http\Enums\Method;
use PHPNomad\Http\Interfaces\Request;
use PHPNomad\Http\Interfaces\Response;
use PHPNomad\Rest\Interfaces\Controller;
use PHPNomad\Rest\Interfaces\HasMiddleware;
use PHPNomad\Rest\Middleware\SetTypeMiddleware;
use PHPNomad\Rest\Enums\BasicTypes;

final class GetUserProfile implements Controller, HasMiddleware
{
    public function __construct(private Response $response, private UserService $users) {}

    public function getEndpoint(): string
    {
        return '/users/{userId}';
    }

    public function getMethod(): string
    {
        return Method::Get;
    }

    public function getResponse(Request $request): Response
    {
        // Because SetTypeMiddleware coerced 'userId',
        // this will always be an integer.
        $user = $this->users->findById($request->getParam('userId'));

        if (!$user) {
            return $this->response
                ->setStatus(404)
                ->setJson(['error' => 'User not found']);
        }

        return $this->response
            ->setStatus(200)
            ->setJson($user);
    }

    public function getMiddleware(Request $request): array
    {
        return [
            new SetTypeMiddleware('userId', BasicTypes::Integer),
        ];
    }
}
```