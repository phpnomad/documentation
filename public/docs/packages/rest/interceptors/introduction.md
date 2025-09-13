# Interceptors

Interceptors allow you to extend and customize the final stage of the request lifecycle in PHPNomad. They run after the
controller has returned its response but before that response is sent back to the client. This makes them ideal for
adapting outputs and handling cross-cutting concerns without bloating controller logic.

Unlike middleware, which runs before a controller executes, interceptors operate with full knowledge of the request and
response. This timing makes them uniquely suited for last-minute adjustments and side effects.

Interceptors give you the power to modify responses or trigger side effects at the very edge of the lifecycle. They see
the full picture—the request, the controller result, and the prepared response—allowing them to:

* Adapt responses into consistent formats
* Enforce output policies centrally
* Trigger events, logging, or metrics safely

By keeping controllers lean and letting interceptors handle boundary concerns, your codebase remains portable,
maintainable, and easier to reason about.

## Adapting Responses

One common use case for interceptors is adapting a response into a format that is safe, portable, or consistent across
your API. Instead of forcing every controller to handle response shaping, you can offload that work to an interceptor
that runs at the boundary.

```php
use PHPNomad\Rest\Interfaces\Interceptor;
use PHPNomad\Http\Interfaces\Request;
use PHPNomad\Http\Interfaces\Response;

final class ModelAdapterInterceptor implements Interceptor
{
    public function process(Request $request, Response $response): void
    {
        $body = $response->getBody();

        if (is_object($body) && method_exists($body, 'toArray')) {
            $response->setJson($body->toArray());
        }
    }
}
```

This interceptor ensures that domain models are consistently converted into JSON arrays without controllers needing to
repeat that logic.

**Conclusion:** By using interceptors for response adaptation, you centralize formatting rules and keep controllers
focused only on core business logic.

## Handling Side Effects

Interceptors are also the right place for side effects that depend on the final result of a request. Since they execute
after the response has been prepared, they can safely trigger actions that should not interfere with the controller’s
outcome.

Typical examples include:

* Publishing domain events once a resource is created or updated
* Logging details of completed requests
* Recording metrics for observability

```php
<?php

namespace App\Interceptors;

use PHPNomad\Rest\Interfaces\Interceptor;
use PHPNomad\Http\Interfaces\Request;
use PHPNomad\Http\Interfaces\Response;
use PHPNomad\Logger\Interfaces\LoggerStrategy;

final class LoggingInterceptor implements Interceptor
{
    public function __construct(private LoggerStrategy $logger) {}

    public function process(Request $request, Response $response): void
    {
        $this->logger->info('API Request Completed', [
            'path'   => $request->getPath(),
            'method' => $request->getMethod(),
            'status' => $response->getStatus(),
        ]);
    }
}
```

This interceptor adds to the logger after a request succeeds, without polluting controller code.

By isolating side effects inside interceptors, you keep your controllers deterministic and ensure cross-cutting actions
happen reliably at the right time.

### Ordering and Execution

When you define multiple interceptors for a controller, their order matters. They execute sequentially, and later
interceptors see the modifications made by earlier ones.

This allows you to stack concerns: for example, one interceptor could adapt models into arrays, and another could wrap
all responses into a common envelope.

* Interceptors run in the order returned from `getInterceptors()`.
* Each interceptor receives the final `Request` and `Response`.
* They may mutate the response body, headers, or status.

By controlling interceptor ordering, you can compose response pipelines cleanly without entangling unrelated concerns.

### Best Practices

Interceptors are powerful, but like middleware, they work best when kept focused and predictable.

Because they can mutate responses or trigger external systems, it’s important to apply consistent patterns to avoid
confusion and unintended side effects.

* Keep interceptors **single-purpose** (e.g., one for adaptation, one for events).
* **Don’t hide failures**: catch exceptions from side effects, but log them for observability.
* Use interceptors to **enforce cross-cutting policies** (envelopes, headers, serialization), not domain logic.
* Be explicit about **which interceptors run where**—avoid magical or hidden behaviors.

Following these practices ensures interceptors stay predictable, reusable, and maintainable over time.