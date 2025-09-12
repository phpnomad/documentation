# Controllers

## Why
Controllers are where request handling happens — they turn validated input into responses.

## What
- Implement `getResponse(Request $request): Response`
- Typically call into datastores, services, or other modules
- Define HTTP method and endpoint

## How
- Extend traits like `CreateController`, `UpdateController`, etc.
- Return JSON or structured responses
- Keep controllers thin: delegate to services for business logic
