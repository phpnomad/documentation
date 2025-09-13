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
Route → Middleware → Controller → Response → Interceptors
```

### Route

The **RestStrategy** matches an incoming request to a registered controller based on the HTTP method and path.

* Integration-specific (FastRoute, WordPress, custom).
* Responsible only for dispatching into the portable REST flow.

### Middleware

[Middleware](./middleware/introduction) runs **before** your controller logic.

* Can short-circuit (e.g., fail auth, block a bad request).
* Can enrich context (e.g., inject a current user, parse query filters).
* Runs in defined order, producing a clean setup for the controller.

### Validations

[Validation](./validations/introduction) sets define **input contracts** for the request. These are set using a
middleware, so you can control when
they run.

* Ensure required fields are present.
* Enforce types and formats (e.g., integer IDs, valid emails).
* Failures are collected and returned in a predictable error payload.

### Controller Handle Method

The [controller](./controllers) is the **core of the endpoint**.

* Business logic goes here: read, mutate, return.
* Sees a request context already shaped by middleware and validated inputs.
* Returns a response object, setting the status and payload.

### Interceptors

[Interceptors](./interceptors/introduction) run **after the controller has produced a response** and **before the
response leaves the pipeline**.

They're usually used for two main purposes:

1. **Adapt the response** — reshape or enrich the response object without touching controller code.
2. **Perform side effects** — emit events, write audit logs, push metrics, etc.

Because interceptors sit at the boundary, they’re an ideal place to keep controllers lean while still achieving
consistent output formats and cross-cutting behavior.