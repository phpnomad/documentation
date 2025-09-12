# Middleware

## Why
Middleware lets you transform or validate requests before they reach your controllers.

## What
- Runs early in the request lifecycle
- Can enforce defaults, parse input, or reject invalid requests
- Implements `PHPNomad\Rest\Interfaces\Middleware`

## How
- Write a class implementing `process(Request $request): void`
- Chain multiple middleware in the lifecycle
- Example: Pagination, CSV parsing, or record existence checks
