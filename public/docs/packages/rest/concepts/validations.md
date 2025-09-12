# Validations

## Why
Validations protect your system by ensuring inputs meet business rules.

## What
- Attached to request parameters
- Implement `PHPNomad\Rest\Interfaces\Validation`
- Can throw `ValidationException` with useful error context

## How
- Write `isValid($key, Request $request): bool`
- Provide `getErrorMessage()` and `getType()`
- Example: Uniqueness checks, existence checks
