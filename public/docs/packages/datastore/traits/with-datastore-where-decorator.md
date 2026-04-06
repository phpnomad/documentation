# WithDatastoreWhereDecorator Trait

The `WithDatastoreWhereDecorator` trait provides automatic implementations of the [`DatastoreHasWhere`](/packages/datastore/interfaces/datastore-has-where) interface by delegating to a `$handler` property. It includes both the base `Datastore` methods and the `where()` method for query-builder-style filtering.

## What It Provides

This trait implements four methods:

* `get(array $args = []): iterable` — from `Datastore`
* `save(Model $item): Model` — from `Datastore`
* `delete(Model $item): void` — from `Datastore`
* `where(): DatastoreWhereQuery` — from `DatastoreHasWhere`

All methods delegate to `$this->handler`.

## Requirements

To use this trait, your class must:

1. **Implement `DatastoreHasWhere`** — the trait provides the method bodies.
2. **Have a `$handler` property** — must be of type `DatastoreHandlerHasWhere`.
3. **Initialize the handler** — typically via constructor injection.

## Basic Usage

```php
<?php

use PHPNomad\Datastore\Interfaces\DatastoreHasWhere;
use PHPNomad\Datastore\Interfaces\DatastoreHandlerHasWhere;
use PHPNomad\Datastore\Traits\WithDatastoreWhereDecorator;

final class PostDatastore implements DatastoreHasWhere
{
    use WithDatastoreWhereDecorator;

    public function __construct(
        private DatastoreHandlerHasWhere $handler
    ) {}
}
```

This datastore now supports `get()`, `save()`, `delete()`, and `where()` with zero boilerplate.

## Generated Code

The trait generates code equivalent to:

```php
final class PostDatastore implements DatastoreHasWhere
{
    private DatastoreHandlerHasWhere $handler;

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

    public function where(): DatastoreWhereQuery
    {
        return $this->handler->where();
    }
}
```

## When to Use This Trait

Use `WithDatastoreWhereDecorator` when:

* Your datastore supports complex querying.
* Your Core implementation doesn't add logic—it just delegates to the handler.
* You want to minimize boilerplate in standard implementations.

## When NOT to Use This Trait

Don't use this trait if you need to:

* Wrap the query builder with additional logic.
* Add caching or logging around query execution.
* Transform queries before delegating to the handler.

In these cases, implement the methods manually.

## Example: Custom Logic in `where()`

If you need to wrap the query builder, implement `where()` manually:

```php
final class PostDatastore implements DatastoreHasWhere
{
    use WithDatastoreWhereDecorator;

    public function __construct(
        private DatastoreHandlerHasWhere $handler,
        private LoggerStrategy $logger
    ) {}

    // Override where() to log query construction
    public function where(): DatastoreWhereQuery
    {
        $this->logger->info("Building query for PostDatastore");
        return $this->handler->where();
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
    use WithDatastoreWhereDecorator {
        // Resolve conflict: both traits provide get(), save(), delete()
        WithDatastorePrimaryKeyDecorator::get insteadof WithDatastoreWhereDecorator;
        WithDatastorePrimaryKeyDecorator::save insteadof WithDatastorePrimaryKeyDecorator;
        WithDatastorePrimaryKeyDecorator::delete insteadof WithDatastoreWhereDecorator;
    }
    use WithDatastoreCountDecorator;       // count()

    public function __construct(
        private DatastoreHandlerHasPrimaryKey & 
                DatastoreHandlerHasWhere & 
                DatastoreHandlerHasCounts $handler
    ) {}
}
```

In practice, you'd typically choose **one** trait that provides the base methods (`get`, `save`, `delete`) and add others that don't conflict. For example:

```php
final class PostDatastoreImpl implements PostDatastore
{
    use WithDatastorePrimaryKeyDecorator;  // Provides all base + find()
    // Manually implement where() if needed, or use trait
    
    public function where(): DatastoreWhereQuery
    {
        return $this->handler->where();
    }

    // count() - implement if needed
    public function count(array $args = []): int
    {
        return $this->handler->count($args);
    }
}
```

## Adding Custom Query Methods

You can add domain-specific query methods alongside trait-provided ones:

```php
interface PostDatastore extends DatastoreHasWhere
{
    public function findRecentPublished(int $limit = 10): iterable;
}

final class PostDatastoreImpl implements PostDatastore
{
    use WithDatastoreWhereDecorator;

    public function __construct(
        private DatastoreHandlerHasWhere $handler
    ) {}

    // Custom query method
    public function findRecentPublished(int $limit = 10): iterable
    {
        return $this->where()
            ->equals('status', 'published')
            ->lessThanOrEqual('published_date', new DateTime())
            ->orderBy('published_date', 'DESC')
            ->limit($limit)
            ->getResults();
    }

    // get(), save(), delete(), where() provided by trait
}
```

## Handler Type Requirements

The `$handler` property must implement `DatastoreHandlerHasWhere`:

```php
interface DatastoreHandlerHasWhere extends DatastoreHandler
{
    public function where(): DatastoreWhereQuery;
}
```

This ensures the handler supports query-builder operations.

## Best Practices

* **Use for pure delegation** — if you're adding logic, implement manually.
* **Name the handler `$handler`** — the trait expects this property name.
* **Match handler and interface types** — if you implement `DatastoreHasWhere`, use `DatastoreHandlerHasWhere`.
* **Combine traits carefully** — resolve conflicts when multiple traits provide the same methods.

## What's Next

* [DatastoreHasWhere Interface](/packages/datastore/interfaces/datastore-has-where) — the interface this trait implements
* [WithDatastorePrimaryKeyDecorator](/packages/datastore/traits/with-datastore-primary-key-decorator) — adds `find()` method
* [Query Building](/packages/database/query-building) — how handlers implement query builders
* [Logger Package](/packages/logger/introduction) — LoggerStrategy interface used in examples above
