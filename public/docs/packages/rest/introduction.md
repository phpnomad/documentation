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