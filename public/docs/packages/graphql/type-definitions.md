# Type Definitions

A `TypeDefinition` is one unit of GraphQL schema. It provides an SDL string that declares your types, and a resolver
map that tells the engine which `FieldResolver` to call for fields with custom logic.

## The Interface

```php
interface TypeDefinition
{
    public function getSdl(): string;
    public function getResolvers(): array;
}
```

`getSdl()` returns a GraphQL SDL string. It may define new object types and extend the root `Query` or `Mutation` types
to add fields:

```graphql
type Book { id: ID! title: String! }
extend type Query { books: [Book!]! book(id: ID!): Book }
```

`getResolvers()` returns a nested array keyed by type name → field name → `FieldResolver` class-string. Only fields
with custom logic need entries; all other fields fall through to default property resolution.

```php
public function getResolvers(): array
{
    return [
        'Query' => [
            'books' => BooksResolver::class,
            'book'  => BookByIdResolver::class,
        ],
    ];
}
```

---

## Complete Example

```php
<?php

use PHPNomad\GraphQL\Interfaces\TypeDefinition;

final class BookTypeDefinition implements TypeDefinition
{
    public function getSdl(): string
    {
        return <<<'SDL'
        type Book {
            id:    ID!
            title: String!
        }

        extend type Query {
            books:       [Book!]!
            book(id: ID!): Book
        }
        SDL;
    }

    public function getResolvers(): array
    {
        return [
            'Query' => [
                'books' => BooksResolver::class,
                'book'  => BookByIdResolver::class,
            ],
        ];
    }
}
```

---

## SDL Conventions

The execution engine (e.g. `WebonyxGraphQLStrategy`) pre-seeds the schema with base `Query` and `Mutation` types.
Your type definitions should **extend** them rather than re-define them:

```graphql
# Correct — extend the base Query type
extend type Query { books: [Book!]! }

# Wrong — do not re-declare Query or Mutation
type Query { books: [Book!]! }
```

You can declare as many object types as needed in a single `getSdl()` return value.

---

## Default Property Resolution

For object fields not listed in `getResolvers()`, the engine falls back to default resolution:

1. If `$rootValue` is an array, it reads `$rootValue['fieldName']`.
2. If `$rootValue` is an object, it reads `$rootValue->fieldName` (or calls the getter if `__get` is defined).

This means you only need to write a `FieldResolver` when the default resolution is not sufficient — for example, when
deriving a value from multiple sources or calling a service.

---

## Registering Type Definitions

Register your `TypeDefinition` classes through an
[initializer implementing `HasTypeDefinitions`](/core-concepts/bootstrapping/initializers/graphql-type-definitions).
The bootstrapper will pass each definition into the active `GraphQLStrategy` during application boot.
