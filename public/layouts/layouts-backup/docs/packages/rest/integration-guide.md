# REST Platform Integration Guide

This guide is for engineers wiring PHPNomad controllers into a specific PHP platform with an HTTP runtime. If you’ve
never extended PHPNomad before, start here.

By the end, you’ll have a thin adapter that lets controllers run unchanged on your platform. It will register
controllers with your router, translate your platform’s request into PHPNomad’s `Request`, convert controller `Response`
objects back into your platform’s response, and keep error handling consistent.

You are responsible for a small contract:

* RestStrategy implementation - to map a controller’s `(method, endpoint)` into your router and drive the request
  lifecycle.
* Request implementation - to expose method, path, headers, params, body, and a safe attribute bag without leaking
  platform
  types.
* Response implementation - to set status, headers, and body while leaving serialization to the strategy.
  *(Authentication can be added via middleware, but it’s optional.)*

This is in great shape—clear, concrete, and the two integration styles (platform-led vs strategy-led) come through
nicely. What’s still missing or underspecified are a few “contract” rules that first-time extenders won’t intuit:

* the lifecycle contract (stated explicitly, not just implied),
* the portable endpoint schema and how params get injected,
* deterministic param resolution (route → query → body),
* the error-mapping guarantee and envelope shape,
* who serializes responses (strategy vs adapter) and body-parsing rules,
* what “Auth via middleware” means when the platform already has permission hooks.

Below are short, drop-in blocks you can add to the intro section (kept skimmable, with bullets only where they carry
weight).

## Integration contract

To integrate with PHPNomad, your adapter must follow these rules.

### Lifecycle (required order)

The request must flow in this order. Don’t skip or reorder steps.

```
Route → Middleware → Controller → Response → Interceptors
```

### Endpoint schema (portable)

Controllers declare endpoints like `/widgets/{id}` using only literals and named params. Your adapter translates to the
platform’s DSL **and** injects captured values back into `Request` under the same keys.

* Example: `/widgets/{id}` → WordPress `(?P<id>[\d]+)` or FastRoute `{id:\w+}`
* Inside controllers, `$request->getParam('id')` works the same on every platform.

## Examples

See these existing adapters for reference implementations.

* [FastRoute Integration](https://github.com/phpnomad/fastroute-integration)
* [WordPress Integration](https://github.com/phpnomad/wordpress-integration)

## Approach

The key to setting up PHPNomad's REST implementation with a platform is that you have to implement the Request,
Response, and RestStrategy, and usually Auth as well.

These are all baked into existing platforms, so usually implementing these feels more like adapting the existing
platform into PHPNomad's syntax.

For example in
the [WordPress integration's RestStrategy](https://github.com/phpnomad/wordpress-integration/blob/main/lib/Strategies/RestStrategy.php),
it registers the route like this:

```php
public function registerRoute(callable $controllerGetter)
{
    // Register the route with WordPress.
    add_action('rest_api_init', function () use ($controllerGetter) {
        /** @var Controller $controller */
        $controller = $controllerGetter();
        // Use WordPress's register_rest_route function to register the route.
        register_rest_route(
            $this->restNamespaceProvider->getRestNamespace(),
            $this->convertEndpointFormat($controller->getEndpoint()),
            [
                'methods' => $controller->getMethod(),
                'callback' => fn(WP_REST_Request $request) => $this->handleRequest($controller, new WordPressRequest($request, $this->currentUserResolver->getCurrentUser())),
                'permission_callback' => '__return_true'
            ]
        );
    });
}
```

The `handleRequest` method that actually does the work of adapting the PHPNomad request into a WordPress request.
It does this by passing
a [WordPressRequest](https://github.com/phpnomad/wordpress-integration/blob/main/lib/Rest/Request.php) object, which
implements PHPNomad's `Request` interface. `handleRequest` then runs the controller and converts the response back
into a WordPress response.

[You can see it in action here.](https://github.com/phpnomad/wordpress-integration/blob/main/lib/Strategies/RestStrategy.php#L77-L102),
but the guts of it are in this method, which runs middleware, gets the response, and runs interceptors:

```php
private function wrapCallback(Controller $controller, Request $request): Response
{
    // Maybe process middleware.
    if ($controller instanceof HasMiddleware) {
        Arr::each($controller->getMiddleware($request), fn(Middleware $middleware) => $middleware->process($request));
    }


    /** @var \PHPNomad\Integrations\WordPress\Rest\Response $response */
    $response = $controller->getResponse($request);


    // Maybe process interceptors.
    if ($controller instanceof HasInterceptors) {
        Arr::each($controller->getInterceptors($request, $response), fn(Interceptor $interceptor) => $interceptor->process($request, $response));
    }


    return $response;
}
```

In some cases, the integration is more-loosely built. For example, the fastroute integration specifically targets
making fastroute natively use PHPNomad to handle building a minimalistic REST API that uses PHPNomad.

This requires a bit more code to accomplish, since Fastroute doesn't handle a lot of the necessary aspects for us. In
the case of Fastroute, we created our own registry to store the routes so that we can reference that and handle routing
ourselves. As shown above, other platforms like WordPress or Laravel would handle most of this for us.

Fastroute's `registerRoute` method looks like this:

```php
public function registerRoute(callable $controllerGetter)
{
    $this->registry->set(function () use ($controllerGetter) {
        /** @var Controller $controller */
        $controller = $controllerGetter();


        return [
          $controller->getMethod(),
          $controller->getEndpoint(),
          function ($request) use ($controller) {
              if ($controller instanceof HasMiddleware) {
                  $this->runMiddleware($controller, $request);
              }
              $response = $controller->getResponse($request);
              $this->setRestHeaders($response);


              if ($controller instanceof HasInterceptors) {
                  $this->runInterceptors($controller, $request, $response);
              }


              return $response;
          }
        ];


    });
}
```

The main difference here is that we needed to provide our own routing registry, and we also needed to handle
setting the response headers, since Fastroute doesn't do that for us, however the rest of the logic is very similar to
the WordPress example above.

* Run middleware if the controller has any.
* Get the response from the controller.
* Set the response headers.
* Run interceptors if the controller has any.
* Return the response.