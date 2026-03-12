# Controllers

Controllers are the **heart of a REST endpoint** in PHPNomad. They are where normalized, validated input is turned into
a response. By the time a controller runs, upstream middleware and validations have already shaped and checked the
request, so the controller can focus entirely on business logic.

## Purpose of Controllers

A controller should be **deterministic**: given a request and any injected dependencies, it computes a result and
returns a response with an explicit status. It doesn’t worry about enforcing defaults, validating input, or logging
side effects. Those belong to other phases of the lifecycle (middleware, validations, interceptors). Keeping controllers
focused in this way ensures predictability and portability across different integrations.

## Controller Contract

Every controller implements the `Controller` interface, which requires three methods:

* `getEndpoint()` — returns the path where this controller is mounted (e.g., `/widgets`).
* `getMethod()` — returns the HTTP method (e.g., `Method::Get`, `Method::Post`).
* `getResponse(Request $request)` — the core logic: receives a normalized request, produces a response.

The `Response` contract provides helpers like `setStatus()`, `setJson()`, and `setError()` to clearly shape the output.

## Constructor Injection

Controllers rarely work alone; they almost always call into services. In PHPNomad, you can declare those needs directly
in the constructor. Dependencies like repositories, domain services, or loggers are provided by
the [initializer](/core-concepts/bootstrapping/creating-and-managing-initializers) and
injected automatically.

This makes controllers **testable and explicit**: they declare what they need, and the DI container provides it. No
service location, no global state.

## Example: Basic Controller

Here’s a simple controller that lists widgets with pagination:

```php
<?php

use PHPNomad\Http\Enums\Method;
use PHPNomad\Http\Interfaces\Request;
use PHPNomad\Http\Interfaces\Response;
use PHPNomad\Rest\Interfaces\Controller;

final class ListWidgets implements Controller
{
    public function __construct(
        private Response $response,
        private WidgetRepository $widgets
    ) {}

    public function getEndpoint(): string
    {
        return '/widgets';
    }

    public function getMethod(): string
    {
        return Method::Get;
    }

    public function getResponse(Request $request): Response
    {
        $items = $this->widgets->list(
            limit:  (int) $request->getParam('number'),
            offset: (int) $request->getParam('offset'),
        );

        return $this->response
            ->setStatus(200)
            ->setJson([
                'data'   => $items,
                'number' => $request->getParam('number'),
                'offset' => $request->getParam('offset'),
            ]);
    }
}
````

In this example, the constructor pulls in two dependencies: the response object and a repository. The controller
declares that it responds to `GET /widgets`, and its `getResponse` method simply queries the repository and returns
JSON. The
response is explicit — a `200 OK` with both the data and the paging parameters echoed back — and is shaped through the
`Response` contract’s helpers.

## Signaling Errors

Not every request succeeds. Controllers can throw a `RestException` when they need to signal an error condition. Systems
that implement the REST lifecycle know how to catch these exceptions and turn them into proper HTTP responses:

* The exception’s **code** becomes the HTTP status.
* The **message** becomes the error message returned to the client.
* The **context** array is included in the error payload, allowing structured details about what went wrong.

This means you don’t need to manually build an error `Response` — throwing a `RestException` is enough.

### Example: Throwing a RestException

```php
<?php

use PHPNomad\Http\Enums\Method;
use PHPNomad\Http\Interfaces\Request;
use PHPNomad\Http\Interfaces\Response;
use PHPNomad\Rest\Interfaces\Controller;
use PHPNomad\Rest\Exceptions\RestException;

final class GetWidget implements Controller
{
    public function __construct(
        private Response $response,
        private WidgetRepository $widgets
    ) {}

    public function getEndpoint(): string
    {
        return '/widgets/{id}';
    }

    public function getMethod(): string
    {
        return Method::Get;
    }

    public function getResponse(Request $request): Response
    {
        $id = (int) $request->getParam('id');
        $widget = $this->widgets->find($id);

        if (!$widget) {
            // 404 Not Found with structured error payload
            throw new RestException(
                code: 404,
                message: "Widget $id was not found",
                context: ['id' => $id]
            );
        }

        return $this->response
            ->setStatus(200)
            ->setJson($widget);
    }
}
````

In this example, if the repository returns nothing, the controller throws a `RestException`. The runtime will catch it
and return a **404 Not Found** with a body like:

```json
{
  "error": {
    "message": "Widget 42 was not found",
    "context": {
      "id": 42
    }
  }
}
```

This keeps your controller code clean: you describe the error, and the framework guarantees consistent error responses
with a predictable structure.

## Full Controller Example

In phpnomad/rest, a controller can be as simple as returning a payload — but in most real systems you’ll need
validations, middleware, and interceptors.

The following example (CreateWidget) demonstrates a fully composed controller that uses all the moving parts:

* Validations define input contracts, ensuring required fields are present and correctly typed.
* Middleware transforms and enforces rules before logic runs (e.g., coercing types, checking authorization, validating).
* Interceptors handle side effects after the response is ready, like broadcasting events.
* Controller logic itself focuses only on the business operation (creating a widget).

This pattern is typical for production endpoints: request → middleware → validations → controller → response →
interceptors.

```php
<?php

use PHPNomad\Http\Enums\Method;
use PHPNomad\Http\Interfaces\Request;
use PHPNomad\Http\Interfaces\Response;

use PHPNomad\Rest\Interfaces\Controller;
use PHPNomad\Rest\Interfaces\HasMiddleware;
use PHPNomad\Rest\Interfaces\HasValidations;
use PHPNomad\Rest\Interfaces\HasInterceptors;

use PHPNomad\Rest\Factories\ValidationSet;
use PHPNomad\Rest\Enums\BasicTypes;
use PHPNomad\Rest\Validations\IsType;

use PHPNomad\Rest\Middleware\SetTypeMiddleware;
use PHPNomad\Rest\Middleware\ValidationMiddleware;
use PHPNomad\Rest\Middleware\AuthorizationMiddleware;

use PHPNomad\Auth\Services\AuthPolicyEvaluatorService;
use PHPNomad\Auth\Enums\SessionContexts;
use PHPNomad\Auth\Enums\ActionTypes;
use PHPNomad\Auth\Models\Action;
use PHPNomad\Auth\Models\Policies\SessionTypePolicy;
use PHPNomad\Auth\Models\Policies\UserCanDoActionPolicy;

use PHPNomad\Rest\Interceptors\EventInterceptor;
use PHPNomad\Events\Interfaces\EventStrategy;

/**
 * Example controller showing how to create a resource ("Widget")
 * while using *all* major features of phpnomad/rest:
 * - Validations
 * - Middleware
 * - Interceptors
 *
 * Each piece is isolated but works together in the request lifecycle.
 */
final class CreateWidget implements Controller, HasMiddleware, HasValidations, HasInterceptors
{
    public function __construct(
        private Response $response,               // Response object (DI-provided)
        private WidgetService $widgets,           // Domain service to handle creation
        private AuthPolicyEvaluatorService $auth, // Policy engine for authorization checks
        private EventStrategy $events             // Event dispatcher for interceptors
    ) {}

    /**
     * The HTTP endpoint path where this controller is mounted.
     */
    public function getEndpoint(): string
    {
        return '/widgets';
    }

    /**
     * The HTTP method used for this endpoint.
     */
    public function getMethod(): string
    {
        return Method::Post;
    }

    /**
     * The core controller logic. This only runs if:
     * - Middleware passed (auth, type coercion, etc.)
     * - Validations succeeded
     */
    public function getResponse(Request $request): Response
    {
        // Grab parameters from the request (already type-coerced and validated)
        $id = $this->widgets->create(
            name:   (string) $request->getParam('name'),
            price:  (float)  $request->getParam('price'),
            tags:   (array)  ($request->getParam('tags') ?? [])
        );

        // For creates, 201 is standard (with a body that includes the new resource ID).
        return $this->response
            ->setStatus(201)
            ->setBody(['id' => $id, 'status' => 'created']);
    }

    /**
     * Validations define the *input contract* for this endpoint.
     * Each field maps to a ValidationSet, which can:
     * - be required or optional
     * - enforce type, format, or custom rules
     */
    public function getValidations(): array
    {
        return [
            // "name" is required and must be a string
            'name' => (new ValidationSet())
                ->setRequired()
                ->addValidation(fn() => new IsType(BasicTypes::String)),

            // "price" is required and must be a float
            'price' => (new ValidationSet())
                ->setRequired()
                ->addValidation(fn() => new IsType(BasicTypes::Float)),

            // "tags" is optional, but if present must be an array
            'tags' => (new ValidationSet())
                ->addValidation(fn() => new IsType(BasicTypes::Array)),
        ];
    }

    /**
     * Middleware runs *before* the controller logic.
     * Common uses:
     * - Type coercion (force "price" into a float)
     * - Authorization (policies based on user/session)
     * - Running the validations defined above
     */
    public function getMiddleware(Request $request): array
    {
        return [
            // Ensure "price" param is treated as a float before validation
            new SetTypeMiddleware('price', BasicTypes::Float),

            // AuthorizationMiddleware checks if the current session
            // is allowed to perform the "create widget" action.
            new AuthorizationMiddleware(
                evaluator: $this->auth,
                context:   SessionContexts::Web,
                action:    new Action(ActionTypes::Create, 'widget'),
                policies:  [
                    // Example policies: require a valid web session
                    new SessionTypePolicy(SessionContexts::Web),
                    // and ensure the user can perform this action
                    new UserCanDoActionPolicy(),
                ],
            ),

            // ValidationMiddleware runs the ValidationSets defined in getValidations().
            new ValidationMiddleware($this),
        ];
    }

    /**
     * Interceptors run *after* the response has been created.
     * They never modify the response, only handle side-effects.
     * Here we broadcast a "WidgetCreatedEvent" for other systems to consume.
     */
    public function getInterceptors(Request $request, Response $response): array
    {
        return [
            new EventInterceptor(
                eventGetter: function () use ($request, $response) {
                    return new WidgetCreatedEvent(
                        name:  (string) $request->getParam('name'),
                        price: (float)  $request->getParam('price'),
                        id:    (int)    ($response->getBody()['id'] ?? 0),
                    );
                },
                eventStrategy: $this->events
            ),
        ];
    }
}
```

## Best Practices

When in doubt, lean towards simplicity. These practices help controllers stay predictable:

* Keep controllers lean: orchestrate the request/response, don’t embed business rules.
* Inject services via the constructor so controllers are easy to test and extend.
* Always set a status: don’t rely on defaults, be explicit about success and failure codes.
* Don’t validate or authorize here. Trust [middleware]/packages/rest/middleware/introduction)
  and [validations](/packages/rest/validations/introduction) to handle that
  before the controller runs.

## When to Add Complexity

In real applications, controllers often participate in a richer lifecycle. Middleware handles cross-cutting concerns
like authorization or pagination defaults. Validations define the input contract. Interceptors perform post-response
work like logging or publishing events.

These are declared by implementing additional interfaces on the controller, but their logic lives outside it. This
separation keeps controllers focused on shaping the response while making each piece reusable across endpoints. For a
broader picture of how these phases work together, see
the [request lifecycle](/packages/rest/introduction#the-request-lifecycle).