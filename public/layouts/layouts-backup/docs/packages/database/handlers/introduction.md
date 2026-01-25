# Database Handlers

Database handlers are the **storage implementation layer** of PHPNomad's datastore architecture. They implement the `DatastoreHandler` interface contracts and are responsible for translating high-level datastore operations (like `save()`, `find()`, or `where()`) into concrete database queries, cache interactions, and event broadcasts.

While [datastore interfaces](/packages/datastore/interfaces/introduction) define the **public API** your application depends on, handler interfaces define the **storage contract** that backends must implement. Handlers live in the Service layer and are specific to a storage technology—in this case, SQL databases.

## What Handlers Do

Handlers are where **persistence logic** lives. A database handler:

* Converts models to storage arrays via [ModelAdapter](/packages/datastore/model-adapters).
* Executes SQL queries using [QueryBuilder](/packages/database/query-building).
* Manages table schema via [Table](/packages/database/tables/introduction) definitions.
* Optionally caches results using [CacheableService](/packages/database/caching-and-events).
* Broadcasts events after mutations using [EventStrategy](/packages/database/caching-and-events).

Your [Core datastore implementation](/packages/datastore/core-implementation) delegates to a handler. The handler does the actual work of talking to the database, while the Core class provides the public interface your application uses.

## Handler Interfaces

Just like datastore interfaces, handler interfaces are **composable**. The base `DatastoreHandler` interface provides minimal operations, and you can extend with additional capabilities as needed.

### `DatastoreHandler`

The minimal contract every handler must implement.

```php
interface DatastoreHandler
{
    public function get(array $args = []): iterable;
    public function save(Model $item): Model;
    public function delete(Model $item): void;
}
```

### Extension Interfaces

Handlers can implement additional interfaces to support more operations:

* **`DatastoreHandlerHasPrimaryKey`** — adds `find(int $id): Model` for primary key lookups.
* **`DatastoreHandlerHasWhere`** — adds `where(): DatastoreWhereQuery` for query-builder filtering.
* **`DatastoreHandlerHasCounts`** — adds `count(array $args = []): int` for counting records.

These mirror the [datastore interfaces](/packages/datastore/interfaces/introduction) but live on the storage side.

## Base Handler Implementation: `IdentifiableDatabaseDatastoreHandler`

PHPNomad provides a **base handler class** that implements all the standard handler interfaces and includes built-in support for caching, events, and query building.

**`IdentifiableDatabaseDatastoreHandler`** is the recommended starting point for most database-backed datastores. It implements:

* `DatastoreHandler`
* `DatastoreHandlerHasPrimaryKey`
* `DatastoreHandlerHasWhere`
* `DatastoreHandlerHasCounts`

This means you get `get()`, `save()`, `delete()`, `find()`, `where()`, and `count()` out of the box.

### What you provide

To use `IdentifiableDatabaseDatastoreHandler`, you extend it and provide:

1. **Table definition** — a [Table](/packages/database/tables/introduction) instance that defines your schema.
2. **ModelAdapter** — converts between models and database arrays.
3. **Dependencies** — QueryBuilder, CacheableService, EventStrategy (injected via constructor).

### Example: basic handler

```php
<?php

use PHPNomad\Database\Services\IdentifiableDatabaseDatastoreHandler;

final class PostHandler extends IdentifiableDatabaseDatastoreHandler
{
    public function __construct(
        PostTable $table,
        PostAdapter $adapter,
        QueryBuilder $queryBuilder,
        CacheableService $cache,
        EventStrategy $events
    ) {
        parent::__construct($table, $adapter, $queryBuilder, $cache, $events);
    }
}
```

That's it. This handler now supports all standard operations, with caching and event broadcasting automatically applied to mutations.

## The Handler Lifecycle

When a datastore method is called, the handler follows a consistent lifecycle:

### Read Operations (get, find, where)

```
1. Check cache (if enabled)
2. If cache miss → build SQL query
3. Execute query via QueryBuilder
4. Convert rows to models via Adapter
5. Store in cache (if enabled)
6. Return models
```

### Write Operations (save)

```
1. Convert model to array via Adapter
2. Determine if INSERT or UPDATE (based on primary key)
3. Execute query via QueryBuilder
4. Invalidate cache (if enabled)
5. Broadcast event (if enabled)
6. Return updated model
```

### Delete Operations (delete)

```
1. Extract primary key from model
2. Execute DELETE query via QueryBuilder
3. Invalidate cache (if enabled)
4. Broadcast event (if enabled)
```

This lifecycle is built into `IdentifiableDatabaseDatastoreHandler`. You don't implement it yourself unless you need custom behavior.

## When to Extend Beyond the Base Handler

Most handlers can extend `IdentifiableDatabaseDatastoreHandler` without customization. But you should implement methods manually when you need:

* **Custom query logic** — complex joins, subqueries, or database-specific features.
* **Conditional caching** — cache some queries but not others.
* **Custom event payloads** — enrich events with computed data.
* **Alternative storage** — if you're not using SQL (e.g., REST API, file storage), implement handler interfaces from scratch.

### Example: custom `find()` with join

```php
final class PostHandler extends IdentifiableDatabaseDatastoreHandler
{
    public function find(int $id): Model
    {
        // Custom query that joins authors table
        $row = $this->queryBuilder
            ->select('posts.*, authors.name as author_name')
            ->from('posts')
            ->join('authors', 'posts.author_id', 'authors.id')
            ->where('posts.id', '=', $id)
            ->first();

        if (!$row) {
            throw new RecordNotFoundException("Post {$id} not found");
        }

        return $this->adapter->toModel($row);
    }
}
```

Here we override `find()` to add a join. The base handler's version would work, but this gives us author data in a single query.

## Boilerplate Reduction with `WithDatastoreHandlerMethods`

If you're not extending `IdentifiableDatabaseDatastoreHandler` (e.g., building a REST handler or custom storage backend), you can use the **`WithDatastoreHandlerMethods`** trait to generate standard implementations.

This trait is analogous to the [decorator traits](/packages/datastore/traits/introduction) in the datastore package. It provides default implementations for common handler patterns.

**Example:**

```php
final class CustomPostHandler implements 
    DatastoreHandler,
    DatastoreHandlerHasPrimaryKey
{
    use WithDatastoreHandlerMethods;

    // Trait provides get(), save(), delete(), find() based on
    // abstract methods you define (like getTable(), getAdapter(), etc.)
}
```

This is useful when you need more control than `IdentifiableDatabaseDatastoreHandler` provides but don't want to write everything from scratch.

## Handler Dependencies

Handlers typically depend on several collaborators:

### Required

* **[Table](/packages/database/tables/introduction)** — schema definition (columns, indexes, primary key).
* **[ModelAdapter](/packages/datastore/model-adapters)** — converts between models and storage arrays.
* **[QueryBuilder](/packages/database/query-building)** — builds and executes SQL queries.

### Optional (but recommended)

* **[CacheableService](/packages/database/caching-and-events)** — automatic result caching with invalidation.
* **[EventStrategy](/packages/database/caching-and-events)** — broadcasts events after mutations.

These are injected via the constructor and provided by your initializer.

## Best Practices

When working with database handlers:

* **Extend `IdentifiableDatabaseDatastoreHandler` by default** — it handles the common cases correctly.
* **Override only when necessary** — if the base handler's behavior works, don't replace it.
* **Keep handlers storage-focused** — business logic belongs in services, not handlers.
* **Use caching and events** — they're built in and cost almost nothing to enable.
* **Match handler interfaces to datastore interfaces** — if your datastore implements `DatastoreHasPrimaryKey`, your handler should implement `DatastoreHandlerHasPrimaryKey`.

## What's Next

To understand how handlers fit into the larger architecture, see:

* [Table Definitions](/packages/database/tables/introduction) — define schemas for handlers to use
* [Query Building](/packages/database/query-building) — how handlers execute SQL
* [Caching and Events](/packages/database/caching-and-events) — automatic caching and event broadcasting
* [Datastore Interfaces](/packages/datastore/interfaces/introduction) — the public contracts handlers support
