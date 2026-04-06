# WithDatastorePrimaryKeyDecorator Trait

The `WithDatastorePrimaryKeyDecorator` trait provides automatic implementations of the [`DatastoreHasPrimaryKey`](/packages/datastore/interfaces/datastore-has-primary-key) interface by delegating to a `$handler` property. It includes both the base `Datastore` methods and the `find()` method for primary key lookups.

## What It Provides

This trait implements four methods:

* `get(array $args = []): iterable` — from `Datastore`
* `save(Model $item): Model` — from `Datastore`
* `delete(Model $item): void` — from `Datastore`
* `find(int $id): Model` — from `DatastoreHasPrimaryKey`

All methods delegate to `$this->handler`.

## Requirements

To use this trait, your class must:

1. **Implement `DatastoreHasPrimaryKey`** — the trait provides the method bodies.
2. **Have a `$handler` property** — must be of type `DatastoreHandlerHasPrimaryKey`.
3. **Initialize the handler** — typically via constructor injection.

## Basic Usage

```php
<?php

use PHPNomad\Datastore\Interfaces\DatastoreHasPrimaryKey;
use PHPNomad\Datastore\Interfaces\DatastoreHandlerHasPrimaryKey;
use PHPNomad\Datastore\Traits\WithDatastorePrimaryKeyDecorator;

final class PostDatastore implements DatastoreHasPrimaryKey
{
    use WithDatastorePrimaryKeyDecorator;

    public function __construct(
        private DatastoreHandlerHasPrimaryKey $handler
    ) {}
}
```

This datastore now supports `get()`, `save()`, `delete()`, and `find()` with zero boilerplate.

## Generated Code

The trait generates code equivalent to:

```php
final class PostDatastore implements DatastoreHasPrimaryKey
{
    private DatastoreHandlerHasPrimaryKey $handler;

    public function get(array $args = []): iterable
    {
        return $this->handler->get($args);
    }

    public function save(Model $item): Model
    {
        return $this->handler->save($item);
    }

    public function delete(Model $item): void
    {
        $this->handler->delete($item);
    }

    public function find(int $id): Model
    {
        return $this->handler->find($id);
    }
}
```

## When to Use This Trait

Use `WithDatastorePrimaryKeyDecorator` when:

* Your datastore has a single integer primary key.
* Your Core implementation doesn't add logic—it just delegates to the handler.
* You want to minimize boilerplate in standard implementations.

## When NOT to Use This Trait

Don't use this trait if you need to:

* Add caching, logging, or validation before delegating.
* Transform data between the public API and handler.
* Implement custom behavior in `find()` or other methods.

In these cases, implement the methods manually.

## Example: Custom Logic in `find()`

If you need custom behavior in `find()`, implement it manually and let the trait handle the rest:

```php
final class PostDatastore implements DatastoreHasPrimaryKey
{
    use WithDatastorePrimaryKeyDecorator;

    public function __construct(
        private DatastoreHandlerHasPrimaryKey $handler,
        private LoggerStrategy $logger
    ) {}

    // Override find() with logging
    public function find(int $id): Model
    {
        $this->logger->info("Finding post: {$id}");
        return $this->handler->find($id);
    }

    // get(), save(), delete() provided by trait
}
```

## Combining with Other Decorator Traits

Most datastores implement multiple interfaces. You can combine traits:

```php
interface PostDatastore extends 
    DatastoreHasPrimaryKey,
    DatastoreHasWhere,
    DatastoreHasCounts
{
    // get(), save(), delete(), find(), where(), count()
}

final class PostDatastoreImpl implements PostDatastore
{
    use WithDatastorePrimaryKeyDecorator;  // get(), save(), delete(), find()
    use WithDatastoreWhereDecorator;       // where()
    use WithDatastoreCountDecorator;       // count()

    public function __construct(
        private DatastoreHandlerHasPrimaryKey & 
                DatastoreHandlerHasWhere & 
                DatastoreHandlerHasCounts $handler
    ) {}
}
```

All six methods are now auto-implemented via traits.

## Adding Custom Business Methods

You can add custom methods alongside trait-provided ones:

```php
interface PostDatastore extends DatastoreHasPrimaryKey
{
    public function findPublishedPosts(int $authorId): iterable;
}

final class PostDatastoreImpl implements PostDatastore
{
    use WithDatastorePrimaryKeyDecorator;

    public function __construct(
        private DatastoreHandlerHasPrimaryKey $handler
    ) {}

    // Custom business method
    public function findPublishedPosts(int $authorId): iterable
    {
        return $this->handler->get([
            'author_id' => $authorId,
            'status' => 'published',
        ]);
    }

    // get(), save(), delete(), find() provided by trait
}
```

## Handler Type Requirements

The `$handler` property must implement `DatastoreHandlerHasPrimaryKey`:

```php
interface DatastoreHandlerHasPrimaryKey extends DatastoreHandler
{
    public function find(int $id): Model;
}
```

This ensures the handler supports primary key lookups.

## Best Practices

* **Use for pure delegation** — if you're adding logic, implement manually.
* **Name the handler `$handler`** — the trait expects this property name.
* **Match handler and interface types** — if you implement `DatastoreHasPrimaryKey`, use `DatastoreHandlerHasPrimaryKey`.
* **Combine traits freely** — traits compose cleanly for multiple capabilities.

## What's Next

* [DatastoreHasPrimaryKey Interface](/packages/datastore/interfaces/datastore-has-primary-key) — the interface this trait implements
* [WithDatastoreWhereDecorator](/packages/datastore/traits/with-datastore-where-decorator) — adds query-builder methods
* [Database Handlers](/packages/database/handlers/introduction) — the handler side of the contract
* [Logger Package](/packages/logger/introduction) — LoggerStrategy interface used in examples above
