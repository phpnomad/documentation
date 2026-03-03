# Field Resolvers

A `FieldResolver` resolves a single GraphQL field. Each resolver is a focused class with one responsibility: given a
root value, arguments, and request context, return the field's value.

## The Interface

```php
interface FieldResolver
{
    public function resolve(mixed $rootValue, array $args, ResolverContext $context): mixed;
}
```

* `$rootValue` — the parent object (e.g. for `Book.author`, this is the `Book` array or object).
* `$args` — the field's inline arguments as an associative array.
* `$context` — carries the current `Request` for auth checks, header reads, etc.

---

## Basic Example

A resolver for `Query.books` that returns a static list:

```php
<?php

use PHPNomad\GraphQL\Interfaces\FieldResolver;
use PHPNomad\GraphQL\Interfaces\ResolverContext;

final class BooksResolver implements FieldResolver
{
    public function resolve(mixed $rootValue, array $args, ResolverContext $context): mixed
    {
        return [
            ['id' => '1', 'title' => 'The Pragmatic Programmer'],
            ['id' => '2', 'title' => 'Clean Code'],
        ];
    }
}
```

---

## Using Arguments

A resolver for `Query.book(id: ID!)` that looks up a single book:

```php
<?php

use PHPNomad\GraphQL\Interfaces\FieldResolver;
use PHPNomad\GraphQL\Interfaces\ResolverContext;

final class BookByIdResolver implements FieldResolver
{
    public function resolve(mixed $rootValue, array $args, ResolverContext $context): mixed
    {
        return $this->books->find($args['id']);
    }
}
```

---

## Using the Request Context

`$context->getRequest()` gives you access to the full `Request` object — useful for reading auth tokens, user info, or
custom headers:

```php
final class ViewerResolver implements FieldResolver
{
    public function resolve(mixed $rootValue, array $args, ResolverContext $context): mixed
    {
        $request = $context->getRequest();
        $user = $request->getUser();

        if ($user === null) {
            return null;
        }

        return ['id' => $user->getId(), 'email' => $user->getEmail()];
    }
}
```

---

## Injecting Services

Resolvers are resolved from the DI container at query time, so you can declare service dependencies in the constructor:

```php
final class BooksResolver implements FieldResolver
{
    public function __construct(private readonly BookRepository $books) {}

    public function resolve(mixed $rootValue, array $args, ResolverContext $context): mixed
    {
        return $this->books->findAll();
    }
}
```

Bind your resolver in an initializer's `getClassDefinitions()` if it needs interface-to-concrete wiring:

```php
public function getClassDefinitions(): array
{
    return [
        EloquentBookRepository::class => BookRepository::class,
    ];
}
```

---

## Registering Resolvers

Resolvers are referenced by class-string in a `TypeDefinition`'s `getResolvers()` map. You don't register them
anywhere else — the strategy reads the map and calls `$container->get(ResolverClass::class)` when the field is
requested. See [Type Definitions](/packages/graphql/type-definitions) for the full example.
