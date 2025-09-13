# Middleware

Middleware is the **pre-controller** layer of `phpnomad/rest`. It runs before your controller’s business logic, shaping
the request into something predictable and safe to operate on. Typical uses include setting sane defaults, coercing
types, enriching context, and—when necessary—stopping a request early.

By the time a request reaches your controller, well-behaved middleware should have already done the boring work:
normalize parameters, apply limits, gate obvious failures, and authorize/authenticate the request.

## What middleware is responsible for

Middleware has two clear responsibilities:

1) Prepare the request Normalize or enrich input so controllers can keep their focus. This might be pagination defaults,
   converting a CSV
   into an array, or attaching derived context for later phases.

2) Short-circuit when appropriate If something is clearly wrong (unauthorized, missing resource, exceeded limit),
   middleware can stop the request
   *before* controller code runs. The recommended way to do this is to **throw a `RestException`** with an HTTP status
   code, message, and structured context—the integration will catch it and format the HTTP response consistently.

## What middleware is not

1. It is not where you perform post-response side effects, such as triggering events. That's the job
   of [interceptors](/packages/rest/middleware/interceptors/introduction)
2. It is not where you encode your domain’s validation rules. That's the job
   of [validations](/packages/rest/middleware/validations/introduction).
3. It is not where you write business logic. That's the job of the controller.

## The middleware contract

A middleware class implements the `PHPNomad\Rest\Interfaces\Middleware` interface and defines a single method:

```php
public function process(\PHPNomad\Http\Interfaces\Request $request): void;
````

* **Input:** a normalized `Request` object you can read and mutate
  via `getParam`, `hasParam`, `setParam`, `removeParam`, and friends.
* **Output:** no return value. Either mutate the request in place and return, or throw a `RestException` to
  short-circuit with an error.

Like controllers, middleware can use **constructor injection**. Because instances are created via your initializer and
container, you can request collaborators (repositories, services, strategies) in the constructor without manual wiring.

## Example: pagination defaults

This middleware ensures that all list endpoints have sensible pagination. Controllers don’t need to know anything about
defaults or caps; they simply read `number` and `offset`.

```php
<?php

use PHPNomad\Rest\Interfaces\Middleware;
use PHPNomad\Http\Interfaces\Request;

final class PaginationMiddleware implements Middleware
{
    public function __construct(
        private int $defaultNumber = 10,
        private int $maxNumber = 50
    ) {}

    public function process(Request $request): void
    {
        // Default page size
        if (!$request->hasParam('number')) {
            $request->setParam('number', $this->defaultNumber);
        }

        // Cap page size
        if ((int) $request->getParam('number') > $this->maxNumber) {
            $request->setParam('number', $this->maxNumber);
        }

        // Default offset
        if (!$request->hasParam('offset')) {
            $request->setParam('offset', 0);
        }
    }
}
```

**Why this works well:** controllers can rely on `number` and `offset` existing and living within bounds, without
duplicating that code in every endpoint.

## Example: resolving a user from the request

This middleware looks up a record by ID and attaches the full record to the request. If the user doesn’t exist, it
throws a `RestException` with a `404` status code.

This is a common pattern for endpoints that operate on a specific resource. By the time the controller runs, it can
assume the user exists and focus on the business logic.

The power of this approach is that the logic to fetch the user and handle the "not found" case is isolated in one place,
and can be reused across multiple controllers, regardless of the datastore implementation.

```php
<?php

use PHPNomad\Rest\Interfaces\Middleware;
use PHPNomad\Http\Interfaces\Request;

final class GetRecordFromRequest implements Middleware
{
    public function __construct(
        protected Datastore $datastore,
        protected LoggerStrategy $logger
    ) {}

    public function process(Request $request): void
    {
        $id = $request->getParam('id');
        
        if(!$id) {
            throw new RestException(
                code: 400,
                message: "Missing required 'id' parameter",
                context: []
            );
        }
        
        try{
            // Fetch the record and attach it to the request.
            $request->setParam('record', $this->datastore->find($id));
        } catch(RecordNotFoundException $e) {
            // If the record doesn't exist, stop the request with a 404.
            throw new RestException(
                code: 404,
                message: "User {$id} was not found",
                context: ['id' => $id]
            );
        } catch(DatastoreErrorException $e) {
            // Log the error for internal tracking.
            $this->logger->logException($e);

            // For other errors, throw a 500 with context.
            // Note that the original exception is not exposed to the client.
            throw new RestException(
                code: 500,
                message: "Error fetching user {$id}",
                context: ['id' => $id]
            );
        }
    }
}
```

## Using middleware in your controllers

To attach middleware to a controller, implement the `PHPNomad\Rest\Interfaces\HasMiddleware` interface and define
`getMiddleware()`.

The example below shows a `GetUser` controller that uses the `GetRecordFromRequest` middleware to fetch a user by ID.
This uses the GetRecordFromRequest middleware defined above.

Note that before it passes the request to the middleware, it also
uses [SetTypeMiddleware](/packages/rest/middleware/included-middleware/set-type-middleware) to ensure the `id` parameter is always an integer.

```php
/**
 * Example controller showing how to get a user
 * - Middleware
 *
 * Each piece is isolated but works together in the request lifecycle.
 */
final class GetUser implements Controller, HasMiddleware
{
    public function __construct(
        private Response $response,                       // Response object (DI-provided)
        private UserDatastore $userDatastore,             // User datastore for middleware
        private UserAdapter $userAdapter,                 // User adapter for controller
        private GetRecordFromRequest $getRecordMiddleware // Middleware instance
    ) {}

    /**
     * The HTTP endpoint path where this controller is mounted.
     */
    public function getEndpoint(): string
    {
        return '/user/{id}';
    }

    /**
     * The HTTP method used for this endpoint.
     */
    public function getMethod(): string
    {
        return Method::Get;
    }

    /**
     * The core controller logic. This only runs if:
     * - Middleware passed (auth, type coercion, etc.)
     * - Validations succeeded
     */
    public function getResponse(Request $request): Response
    {
        // The "record" param is set by GetRecordFromRequest middleware.
        // Adapter converts it to a response-friendly format.
        $user = $this->userAdapter->toResponse(
            $request->getParam('record') // Set by middleware
        );

        // For gets, 200 is standard (with a body that includes the resource).
        return $this->response
            ->setStatus(200)
            ->setBody($user);
    }

    /**
     * Middleware runs *before* the controller logic.
     */
    public function getMiddleware(Request $request): array
    {
        return [
            new SetTypeMiddleware('id', BasicTypes::Integer), // Coerce 'id' to int
            $this->getRecordMiddleware
        ];
    }
}
```