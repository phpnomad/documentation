# REST Integration Guide

## Why
For PHPNomad REST to work, platforms must implement specific integration points.

## What
An integration must:
- Provide an HTTP request/response implementation
- Support middleware and validation chaining
- Dispatch to controllers and interceptors

## How
1. Implement the lifecycle contracts from `PHPNomad\Rest\Interfaces`.
2. Ensure platform router dispatches into REST.
3. Add lifecycle components in the correct order.
4. Verify with integration tests against controllers, validations, and interceptors.
