# GraphQL Type Definitions in PHPNomad

GraphQL type definitions define an **SDL fragment and a resolver map** — and they stay portable across engines.

Instead of registering types inline, PHPNomad discovers them through **Initializers** that implement
**`HasTypeDefinitions`**. This keeps your schema modular and decoupled from the execution engine, and mirrors exactly
how you already register REST controllers with `HasControllers`.

## The Basics: How registration works

* **TypeDefinition**: a class implementing `TypeDefinition` — provides `getSdl()` and `getResolvers()`.
* **Initializer implementing `HasTypeDefinitions`**: returns a list of `TypeDefinition` class-strings to load.
* **Bootstrapper** configured to utilize the initializer.
  See [Creating and Managing Initializers](/core-concepts/bootstrapping/creating-and-managing-initializers) for more
  information.

The container constructs `TypeDefinition` instances (resolving dependencies), and the active `GraphQLStrategy`
accumulates them to build the schema.

## Minimal TypeDefinition

```php
<?php

use PHPNomad\GraphQL\Interfaces\TypeDefinition;

final class BookTypeDefinition implements TypeDefinition
{
    public function getSdl(): string
    {
        return <<<'SDL'
        type Book { id: ID! title: String! }
        extend type Query { books: [Book!]! book(id: ID!): Book }
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

See the [Type Definitions](/packages/graphql/type-definitions) package documentation for fuller examples.

## Registering Type Definitions via an Initializer

Your initializer **implements `HasTypeDefinitions`** and returns the definitions to load. The container instantiates
them; the `GraphQLStrategy` reads `getSdl()` / `getResolvers()` to build the schema.

```php
<?php

use PHPNomad\GraphQL\Interfaces\HasTypeDefinitions;

final class GraphQLTypesInitializer implements HasTypeDefinitions
{
    /**
     * Return the list of TypeDefinitions to register.
     *
     * @return array<class-string<TypeDefinition>>
     */
    public function getTypeDefinitions(): array
    {
        return [
            BookTypeDefinition::class,
            // AuthorTypeDefinition::class,
            // ReviewTypeDefinition::class,
        ];
    }
}
```
