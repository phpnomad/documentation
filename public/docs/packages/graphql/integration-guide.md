# GraphQL Platform Integration Guide

This guide is for engineers wiring `phpnomad/graphql` into a specific PHP runtime or HTTP framework. By the end you
will have a thin adapter that:

1. Implements `GraphQLStrategy` to execute queries against a built schema.
2. Implements `ResolverContext` to carry the platform's request through the execution.
3. Exposes a single HTTP endpoint (typically `POST /graphql`) that calls `execute()` and returns JSON.

If you're using webonyx/graphql-php, a ready-made implementation is available in
[phpnomad/webonyx-integration](/packages/graphql/webonyx-integration). Use this guide only if you need a different
engine.

---

## Contracts you must implement

### `GraphQLStrategy`

```php
interface GraphQLStrategy
{
    public function registerTypeDefinition(callable $definitionGetter): void;
    public function execute(string $query, array $variables, ResolverContext $context): array;
}
```

* `registerTypeDefinition()` — called once per `TypeDefinition` class during boot (via `HasTypeDefinitions`). Store the
  getter; build the schema lazily on first `execute()` call.
* `execute()` — parse the query, resolve against the schema, and return a plain PHP array in the GraphQL wire format:
  `['data' => [...]]` on success or `['errors' => [...]]` on failure.

### `ResolverContext`

```php
interface ResolverContext
{
    public function getRequest(): Request;
}
```

Wrap your platform's request object in a class that implements `ResolverContext`. The context instance is passed to
every `FieldResolver::resolve()` call, so resolvers can access auth, headers, and params uniformly.

---

## Minimal skeleton

```php
<?php

use PHPNomad\GraphQL\Interfaces\GraphQLStrategy;
use PHPNomad\GraphQL\Interfaces\ResolverContext;
use PHPNomad\GraphQL\Interfaces\TypeDefinition;

final class MyGraphQLStrategy implements GraphQLStrategy
{
    /** @var callable[] */
    private array $typeDefinitionGetters = [];
    private ?object $schema = null;  // use your engine's Schema type

    public function __construct(private readonly InstanceProvider $container) {}

    public function registerTypeDefinition(callable $definitionGetter): void
    {
        $this->typeDefinitionGetters[] = $definitionGetter;
        $this->schema = null;  // invalidate cached schema
    }

    public function execute(string $query, array $variables, ResolverContext $context): array
    {
        if ($this->schema === null) {
            $this->schema = $this->buildSchema();
        }

        // call your engine's execution method here
        return $this->engine->executeQuery($this->schema, $query, $variables, $context)->toArray();
    }

    private function buildSchema(): object
    {
        $resolverMap = [];
        $sdlParts = [];

        foreach ($this->typeDefinitionGetters as $getter) {
            /** @var TypeDefinition $def */
            $def = ($getter)();
            $sdlParts[] = $def->getSdl();

            foreach ($def->getResolvers() as $type => $fields) {
                foreach ($fields as $field => $resolverClass) {
                    $resolverMap[$type][$field] = $resolverClass;
                }
            }
        }

        $fullSdl = implode("\n", $sdlParts);
        $container = $this->container;

        // pass $resolverMap and $container to your engine's schema builder
        return $this->engine->buildSchema($fullSdl, $resolverMap, $container);
    }
}
```

---

## Binding the strategy

In an initializer, bind your concrete strategy to the `GraphQLStrategy` interface:

```php
public function getClassDefinitions(): array
{
    return [
        MyGraphQLStrategy::class => GraphQLStrategy::class,
    ];
}
```

---

## HTTP endpoint

The standard pattern is a single `POST /graphql` endpoint that:

1. Reads `query` and `variables` from the JSON request body.
2. Builds a `ResolverContext` from the platform request.
3. Calls `$strategy->execute()` and returns the result as JSON.

```php
$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$query    = $body['query'] ?? '';
$variables = $body['variables'] ?? [];

$context = new MyResolverContext($platformRequest);
$result  = $strategy->execute($query, $variables, $context);

header('Content-Type: application/json');
echo json_encode($result);
```

---

## See also

* [Type Definitions](/packages/graphql/type-definitions)
* [Field Resolvers](/packages/graphql/field-resolvers)
* [GraphQL Type Definitions in initializers](/core-concepts/bootstrapping/initializers/graphql-type-definitions)
* [phpnomad/webonyx-integration](/packages/graphql/webonyx-integration)
