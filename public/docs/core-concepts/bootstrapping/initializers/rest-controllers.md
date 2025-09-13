# REST Controllers in PHPNomad

REST controllers define an **endpoint**, an **HTTP method**, and **how to produce a response**—and they stay portable
across hosts.

Instead of registering routes inline, PHPNomad discovers them through **Initializers** that implement **`HasControllers`
**. This keeps your API definitions decoupled from the host and mirrors how you already register event listeners
with `HasListeners`.

## The Basics: How registration works

* **Controller**: a small class implementing, at minimum, `Controller`
* **Initializer** implementing `HasControllers`: returns a list of controllers to load.
* **Boostrapper** configured to utilize the initializer.
  See [Creating and Managing Initializers](/core-concepts/bootstrapping/creating-and-managing-initializers) for more
  information

The container will construct these controllers (resolving dependencies), and the runtime will register their routes from
the controller contracts.

## Minimal Controller

This is a very basic example of a controller, and does not include middleware or validations. In your production
controllers, you’ll often also implement `HasMiddleware` / `HasValidations`. See real-world examples
where `getEndpoint()`, `getMethod()`, and `getValidations()` are used together.

See the [Rest](/packages/rest/introduction) package documentation for fuller examples.

```php
<?php

use PHPNomad\Http\Interfaces\Request;
use PHPNomad\Http\Interfaces\Response;
use PHPNomad\Rest\Interfaces\Controller;

final class HelloController implements Controller
{
    public function __construct(private Response $response) {}

    public function getEndpoint(): string
    {
        return '/hello';
    }

    public function getMethod(): string
    {
        return 'GET';
    }

    public function getResponse(Request $request): Response
    {
        return $this->response
            ->setStatus(200)
            ->setJson(['message' => 'Hello from PHPNomad REST']);
    }
}
```

## Registering Controllers via an Initializer

Your initializer simply **implements `HasControllers`** and returns the controllers to load.
The container instantiates them; the runtime reads `getEndpoint()` / `getMethod()` to wire routes.

```php
<?php
// initializers/RestControllersInitializer.php

use PHPNomad\Loader\Interfaces\Initializer;
// use Your\Project\Interfaces\HasControllers;  // ← import the HasControllers interface from your project
// (Namespace may vary by codebase; keep it consistent with your other initializers.)

final class RestControllersInitializer implements Initializer, HasControllers
{
    /**
     * Return the list of Controllers to be mounted.
     * You can return class-strings for DI instantiation, or instances if you prefer.
     *
     * @return array<class-string<Controller>|Controller>
     */
    public function getControllers(): array
    {
        return [
            HelloController::class,
            // CreateWidget::class,
            // GetWidget::class,
            // ListWidgets::class,
        ];
    }
}
```