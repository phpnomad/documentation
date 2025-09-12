# Rest

`phpnomad/rest` is an **MVC-driven methodology for defining REST APIs**.
It’s designed to let you describe **controllers, routes, validations, and policies** in a way that’s **agnostic to the
framework or runtime** you plug into.

At its core:

* **Controllers** express your business logic in a consistent contract.
* **RestStrategies** adapt those controllers to different environments (FastRoute, WordPress, custom).
* **Middleware** and **Validations** give you predictable, portable contracts around input handling and authorization.
* **Interceptors** capture side effects like events or logs after responses are sent.

By separating API **definition** (what the endpoint is, what it requires, what it returns) from **integration** (how it
runs inside a host), you get REST endpoints that can move between stacks without rewrites.

## Key ideas at a glance

* **Controller** — your endpoint’s logic, returning the payload + status intent.
* **RestStrategy** — wires routes to controllers in your host framework.
* **Middleware** — pre-controller cross-cuts (auth, pagination defaults, projections).
* **Validations** — input contracts with a consistent error shape.
* **Interceptors** — post-response side effects (events, logs, metrics).

---

## The Request Lifecycle

When a request enters a system wired with `phpnomad/rest`, it moves through a consistent sequence of steps:

```
Route → Middleware → Validations → Controller → Response → Interceptors
```

### Route

The **RestStrategy** matches an incoming request to a registered controller based on the HTTP method and path.

* Integration-specific (FastRoute, WordPress, custom).
* Responsible only for dispatching into the portable REST flow.

### Middleware

Middleware runs **before** your controller logic.

* Can short-circuit (e.g., fail auth, block a bad request).
* Can enrich context (e.g., inject a current user, parse query filters).
* Runs in defined order, producing a clean setup for the controller.

### Validations

Validation sets define **input contracts** for the request.

* Ensure required fields are present.
* Enforce types and formats (e.g., integer IDs, valid emails).
* Failures are collected and returned in a predictable error payload.

### Controller Handle Method

The controller is the **core of the endpoint**.

* Business logic goes here: read, mutate, return.
* Sees a request context already shaped by middleware and validated inputs.
* Returns a response object, setting the status and payload.

### Interceptors

Interceptors run **after the response is prepared**.

* Side effects only — they do not change the response sent to the client.
* Common uses: emit domain events, write audit logs, push metrics.
* Errors here are isolated so they don’t break the response lifecycle.

## Controller Example

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