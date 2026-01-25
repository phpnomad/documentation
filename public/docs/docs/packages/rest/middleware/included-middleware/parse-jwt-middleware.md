# ParseJwtMiddleware

The `ParseJwtMiddleware` is a built-in middleware in PHPNomad designed to handle **JSON Web Tokens (JWTs)**.  
Its job is to read a JWT from the request, validate and decode it, and make the decoded token available
to downstream parts of the lifecycle (controllers, other middleware, etc.).

## Purpose

Authentication and authorization flows often require a token that represents the identity and claims of the current user.
The `ParseJwtMiddleware` ensures:

- The token is present in the request (under a configurable key).
- It is decoded and validated using the configured `JwtService`.
- If valid, the decoded token is re-attached to the request for later use.
- If invalid, a `RestException` is thrown so the request ends early with a clear error response.

This keeps your controllers and other components free from manual JWT parsing and error handling.

## Usage

In practice, you don’t call middleware directly — you declare it on a controller.
Here’s how to attach `ParseJwtMiddleware` to an endpoint that requires a valid token:

```php
<?php

use PHPNomad\Http\Enums\Method;
use PHPNomad\Http\Interfaces\Request;
use PHPNomad\Http\Interfaces\Response;
use PHPNomad\Rest\Interfaces\Controller;
use PHPNomad\Rest\Interfaces\HasMiddleware;
use PHPNomad\Rest\Middleware\ParseJwtMiddleware;

final class GetProfile implements Controller, HasMiddleware
{
    public function __construct(
        private Response $response,
        private ParseJwtMiddleware $jwtMiddleware
    ) {}

    public function getEndpoint(): string
    {
        return '/profile';
    }

    public function getMethod(): string
    {
        return Method::Get;
    }

    public function getResponse(Request $request): Response
    {
        // Because ParseJwtMiddleware has run,
        // 'jwt' now contains the decoded token payload.
        $token = $request->getParam('jwt');

        return $this->response
            ->setStatus(200)
            ->setJson([
                'userId' => $token['sub'],
                'roles'  => $token['roles'] ?? [],
            ]);
    }

    public function getMiddleware(Request $request): array
    {
        return [
            $this->jwtMiddleware,
        ];
    }
}
```

### Example request

```
GET /profile?jwt=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

### Example response

```json
{
  "userId": 123,
  "roles": ["editor", "admin"]
}
```

If the token is invalid, the response would instead be:

```json
{
  "error": {
    "message": "Invalid Token",
    "context": {}
  }
}
```

with status **400 Bad Request**.

---

## Best Practices

* **Chain this early**: Place `ParseJwtMiddleware` before any logic that relies on user identity.
* **Keep controllers lean**: Once decoded, controllers should just consume `$request->getParam('jwt')`.
* **Consistent key**: If your API passes the token under a different request key, configure `ParseJwtMiddleware` with that key.
* **Fail fast**: By throwing a `RestException` early, you prevent controllers from ever running with bad state.