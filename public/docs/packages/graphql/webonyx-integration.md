# webonyx Integration

`phpnomad/webonyx-integration` is the concrete `GraphQLStrategy` implementation backed
by [`webonyx/graphql-php`](https://github.com/webonyx/graphql-php). It implements all the contracts defined in
`phpnomad/graphql` so you can drop it in without writing any engine code yourself.

## Installation

```bash
composer require phpnomad/webonyx-integration
```

This pulls in `webonyx/graphql-php ^15.0`, `phpnomad/graphql`, and `phpnomad/di` automatically.

---

## `WebonyxGraphQLStrategy`

`PHPNomad\GraphQL\Webonyx\Strategies\WebonyxGraphQLStrategy` implements `GraphQLStrategy`. Its constructor takes an
`InstanceProvider` (the DI container) and uses it to resolve `FieldResolver` classes at query time.

```php
$strategy = new WebonyxGraphQLStrategy($container);
```

### How schema building works

On first `execute()` call the strategy:

1. Calls each registered `TypeDefinition` getter to obtain a definition instance.
2. Starts with a base SDL that declares empty-safe `Query` and `Mutation` roots.
3. Appends each definition's `getSdl()`.
4. Merges all `getResolvers()` maps into a single type → field → class-string table.
5. Calls `BuildSchema::build($fullSdl, $typeConfigDecorator)` where the decorator attaches a `resolveField` closure to
   any type with registered resolvers.
6. The `resolveField` closure resolves the resolver class from the container and calls `resolve()`. Fields with no
   entry fall back to `Executor::defaultFieldResolver`.

The schema is cached after first build. Calling `registerTypeDefinition()` again invalidates the cache so the next
`execute()` rebuilds.

---

## Binding the strategy

In an initializer, bind `WebonyxGraphQLStrategy` to the `GraphQLStrategy` interface:

```php
<?php

use PHPNomad\GraphQL\Interfaces\GraphQLStrategy;
use PHPNomad\GraphQL\Webonyx\Strategies\WebonyxGraphQLStrategy;

final class GraphQLInitializer implements HasClassDefinitions
{
    public function getClassDefinitions(): array
    {
        return [
            WebonyxGraphQLStrategy::class => GraphQLStrategy::class,
        ];
    }
}
```

---

## Full bootstrap example

```php
<?php

use PHPNomad\GraphQL\Interfaces\GraphQLStrategy;
use PHPNomad\GraphQL\Webonyx\Strategies\WebonyxGraphQLStrategy;
use PHPNomad\Di\Container;

// 1. Container
$container = new Container();
$container->bindSingleton(WebonyxGraphQLStrategy::class, GraphQLStrategy::class);

// 2. Strategy
$strategy = $container->get(GraphQLStrategy::class);

// 3. Register a type definition (normally done via HasTypeDefinitions initializer)
$strategy->registerTypeDefinition(fn() => new BookTypeDefinition());

// 4. Execute
$result = $strategy->execute('{ books { id title } }', [], $context);
echo json_encode($result);
// → {"data":{"books":[{"id":"1","title":"The Pragmatic Programmer"},{"id":"2","title":"Clean Code"}]}}
```

---

## Implementing `ResolverContext`

`WebonyxGraphQLStrategy` passes the `ResolverContext` you supply directly to every `FieldResolver::resolve()` call as
the third argument. You implement it once to wrap your platform's request:

```php
<?php

use PHPNomad\GraphQL\Interfaces\ResolverContext;
use PHPNomad\Http\Interfaces\Request;

final class HttpResolverContext implements ResolverContext
{
    public function __construct(private readonly Request $request) {}

    public function getRequest(): Request
    {
        return $this->request;
    }
}
```

---

## See also

* [Type Definitions](/packages/graphql/type-definitions)
* [Field Resolvers](/packages/graphql/field-resolvers)
* [Integration Guide](/packages/graphql/integration-guide)
* [GraphQL Type Definitions in initializers](/core-concepts/bootstrapping/initializers/graphql-type-definitions)
