# WithDatastoreCountDecorator Trait

The `WithDatastoreCountDecorator` trait provides automatic implementations of the [`DatastoreHasCounts`](/packages/datastore/interfaces/datastore-has-counts) interface by delegating to a `$handler` property. It includes both the base `Datastore` methods and the `count()` method for efficient record counting.

## What It Provides

This trait implements four methods:

* `get(array $args = []): iterable` — from `Datastore`
* `save(Model $item): Model` — from `Datastore`
* `delete(Model $item): void` — from `Datastore`
* `count(array $args = []): int` — from `DatastoreHasCounts`

All methods delegate to `$this->handler`.

## Requirements

To use this trait, your class must:

1. **Implement `DatastoreHasCounts`** — the trait provides the method bodies.
2. **Have a `$handler` property** — must be of type `DatastoreHandlerHasCounts`.
3. **Initialize the handler** — typically via constructor injection.

## Basic Usage

```php
<?php

use PHPNomad\Datastore\Interfaces\DatastoreHasCounts;
use PHPNomad\Datastore\Interfaces\DatastoreHandlerHasCounts;
use PHPNomad\Datastore\Traits\WithDatastoreCountDecorator;

final class PostDatastore implements DatastoreHasCounts
{
    use WithDatastoreCountDecorator;

    public function __construct(
        private DatastoreHandlerHasCounts $handler
    ) {}
}
```

This datastore now supports `get()`, `save()`, `delete()`, and `count()` with zero boilerplate.

## Generated Code

The trait generates code equivalent to:

```php
final class PostDatastore implements DatastoreHasCounts
{
    private DatastoreHandlerHasCounts $handler;

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

    public function count(array $args = []): int
    {
        return $this->handler->count($args);
    }
}
```

## When to Use This Trait

Use `WithDatastoreCountDecorator` when:

* Your datastore needs efficient counting operations.
* Your Core implementation doesn't add logic—it just delegates to the handler.
* You want to minimize boilerplate in standard implementations.

## When NOT to Use This Trait

Don't use this trait if you need to:

* Add caching around count operations.
* Log or track count queries.
* Transform count criteria before delegating.

In these cases, implement the methods manually.

## Example: Custom Logic in `count()`

If you need custom behavior in `count()`, implement it manually:

```php
final class PostDatastore implements DatastoreHasCounts
{
    use WithDatastoreCountDecorator;

    public function __construct(
        private DatastoreHandlerHasCounts $handler,
        private CacheService $cache
    ) {}

    // Override count() with caching
    public function count(array $args = []): int
    {
        $cacheKey = 'post_count_' . md5(serialize($args));
        
        return $this->cache->remember($cacheKey, 300, function() use ($args) {
            return $this->handler->count($args);
        });
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
    use WithDatastoreCountDecorator {
        // Resolve conflict: both traits provide get(), save(), delete()
        WithDatastorePrimaryKeyDecorator::get insteadof WithDatastoreCountDecorator;
        WithDatastorePrimaryKeyDecorator::save insteadof WithDatastoreCountDecorator;
        WithDatastorePrimaryKeyDecorator::delete insteadof WithDatastoreCountDecorator;
    }

    public function __construct(
        private DatastoreHandlerHasPrimaryKey & 
                DatastoreHandlerHasCounts $handler
    ) {}

    // Manually implement where()
    public function where(): DatastoreWhereQuery
    {
        return $this->handler->where();
    }
}
```

## Adding Custom Count Methods

You can add domain-specific count methods alongside trait-provided ones:

```php
interface PostDatastore extends DatastoreHasCounts
{
    public function countByAuthor(int $authorId): int;
    public function countPublished(): int;
}

final class PostDatastoreImpl implements PostDatastore
{
    use WithDatastoreCountDecorator;

    public function __construct(
        private DatastoreHandlerHasCounts $handler
    ) {}

    // Custom count method
    public function countByAuthor(int $authorId): int
    {
        return $this->count(['author_id' => $authorId]);
    }

    // Another custom count method
    public function countPublished(): int
    {
        return $this->count(['status' => 'published']);
    }

    // get(), save(), delete(), count() provided by trait
}
```

## Handler Type Requirements

The `$handler` property must implement `DatastoreHandlerHasCounts`:

```php
interface DatastoreHandlerHasCounts extends DatastoreHandler
{
    public function count(array $args = []): int;
}
```

This ensures the handler supports efficient counting operations.

## Best Practices

* **Use for pure delegation** — if you're adding logic, implement manually.
* **Name the handler `$handler`** — the trait expects this property name.
* **Match handler and interface types** — if you implement `DatastoreHasCounts`, use `DatastoreHandlerHasCounts`.
* **Combine traits carefully** — resolve conflicts when multiple traits provide the same methods.

## What's Next

* [DatastoreHasCounts Interface](/packages/datastore/interfaces/datastore-has-counts) — the interface this trait implements
* [WithDatastorePrimaryKeyDecorator](/packages/datastore/traits/with-datastore-primary-key-decorator) — adds `find()` method
* [Database Handlers](/packages/database/handlers/introduction) — the handler side of the contract
