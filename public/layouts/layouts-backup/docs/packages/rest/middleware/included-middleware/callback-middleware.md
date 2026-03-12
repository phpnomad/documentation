# CallbackMiddleware

The `CallbackMiddleware` is the most minimal middleware provided in PHPNomad. It exists as a utility for injecting
arbitrary logic into the request lifecycle without creating a dedicated middleware class.

It’s particularly useful for **simple one-off behaviors**, rapid prototyping, or cases where full-blown middleware is
unnecessary.

## Purpose

Middleware normally provides **reusable, named behaviors** that can be applied across multiple controllers. For example,
pagination defaults or record existence checks.

However, sometimes you need lightweight logic that doesn’t justify creating and registering a new class.
The `CallbackMiddleware` makes this possible by letting you pass in any callable and have it run against the request.

This trades **formality for flexibility**. Use it sparingly, but it can be a good tool for fast iteration.

## Contract

`CallbackMiddleware` implements the standard `Middleware` interface:

```php
interface Middleware
{
    public function process(Request $request): void;
}
```

Instead of having its own logic, it simply wraps a user-supplied `callable` and invokes it during `process()`:

```php
final class CallbackMiddleware implements Middleware
{
    public function __construct(callable $callback) { /* ... */ }

    public function process(Request $request): void
    {
        ($this->callback)($request);
    }
}
```

The callback receives the **normalized request** object, allowing you to inspect or modify it before the controller
executes.

## Example: Adding a Default Parameter

Here’s how you could use `CallbackMiddleware` to inject a default `locale` if the request does not already have one:

```php
use PHPNomad\Rest\Middleware\CallbackMiddleware;
use PHPNomad\Http\Interfaces\Request;

$middleware = new CallbackMiddleware(function (Request $request) {
    if (!$request->hasParam('locale')) {
        $request->setParam('locale', 'en_US');
    }
});
```

When included in a controller’s middleware chain, this ensures every request has a `locale` parameter available.

## Example: Simple Audit Logging

You could also log requests inline without creating a full logger middleware:

```php
use PHPNomad\Rest\Middleware\CallbackMiddleware;
use PHPNomad\Http\Interfaces\Request;

$middleware = new CallbackMiddleware(function (Request $request) {
    error_log("Incoming request to: " . $request->getParam('endpoint'));
});
```

This is useful for debugging or quick metrics collection.

## Best Practices

* **Prefer explicit middleware classes** for reusable or complex behaviors.
* **Use CallbackMiddleware only for simple, localized logic** where creating a full middleware class would be overkill.
* **Keep callbacks short and focused** — they should not contain business logic or validation rules.
