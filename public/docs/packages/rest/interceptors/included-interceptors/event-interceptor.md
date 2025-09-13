# EventInterceptor

The `EventInterceptor` is a built-in interceptor in PHPNomad designed for **publishing events** after a controller has
completed its work. It lets you broadcast domain events in response to API calls, keeping controllers free of
side-effect logic.

## Purpose

Controllers should remain deterministic: given valid input, return a response. But many actions in a system also trigger
**side effects**—for example, creating a resource may need to emit a `UserRegistered` or `OrderPlaced` event.

Placing this responsibility inside controllers creates tight coupling and scattered event code. The `EventInterceptor`
moves this logic into the lifecycle boundary, where it can consistently fire after the response is prepared.

## How It Works

The interceptor accepts two things at construction:

* **`$eventGetter`** — a callable that produces an `Event` instance, usually using data from the request or response.
* **`$eventStrategy`** — the broadcasting mechanism that knows how to publish events to your system (e.g., sync
  dispatch, queue, message bus).

When the interceptor runs, it calls the getter, builds the event, and uses the strategy to broadcast it.

## Usage Example

Here’s how you could use `EventInterceptor` to broadcast an event whenever a new widget is created:

```php
<?php

use PHPNomad\Http\Enums\Method;
use PHPNomad\Http\Interfaces\Request;
use PHPNomad\Http\Interfaces\Response;

use PHPNomad\Rest\Interfaces\Controller;
use PHPNomad\Rest\Interfaces\HasInterceptors;
use PHPNomad\Rest\Interceptors\EventInterceptor;
use PHPNomad\Events\Interfaces\EventStrategy;

final class CreateWidget implements Controller, HasInterceptors
{
    public function __construct(
        private Response $response,
        private WidgetService $widgets,
        private EventStrategy $events
    ) {}

    public function getEndpoint(): string { return '/widgets'; }
    public function getMethod(): string   { return Method::Post; }

    public function getResponse(Request $request): Response
    {
        $id = $this->widgets->create(
            name: (string) $request->getParam('name')
        );

        return $this->response
            ->setStatus(201)
            ->setJson(['id' => $id, 'status' => 'created']);
    }

    public function getInterceptors(Request $req, Response $res): array
    {
        return [
            new EventInterceptor(
                eventGetter: fn () => new WidgetCreatedEvent(
                    id: $res->getBody()['id'],
                    name: $req->getParam('name')
                ),
                eventStrategy: $this->events
            ),
        ];
    }
}
```

In this example:

* The controller only creates the widget and returns a response.
* The interceptor handles broadcasting a `WidgetCreatedEvent` after the response is finalized.
* Event emission is kept portable, reusable, and out of controller code.

The `EventInterceptor` provides a clean way to emit domain events at the edge of the request lifecycle. By using a
simple getter and a broadcasting strategy, you keep controllers lean, avoid duplicated event logic, and ensure side
effects happen reliably after the main response is prepared.