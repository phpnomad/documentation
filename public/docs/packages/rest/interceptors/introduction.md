# Interceptors

## Why
Interceptors allow you to hook into the end of the request lifecycle.

## What
- Runs after controller response is generated
- Ideal for logging, events, caching, transformations
- Implement `PHPNomad\Rest\Interfaces\Interceptor`

## How
- Define `process(Request $request, Response $response): void`
- Attach interceptors in platform setup
- Keep them idempotent and side-effect-aware
