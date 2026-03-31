# IdentifiableDatabaseDatastoreHandler

The `IdentifiableDatabaseDatastoreHandler` is the **base class** for implementing database-backed datastores in PHPNomad. It provides complete implementations of all standard handler interfaces (find, get, save, delete, where, count) with built-in caching, event broadcasting, and query building.

When you extend this class, you get a fully functional database handler with minimal code—just set a few properties in your constructor.

## What It Provides

By extending `IdentifiableDatabaseDatastoreHandler`, your handler automatically implements:

* **DatastoreHandler** — `get()`, `save()`, `delete()`
* **DatastoreHandlerHasPrimaryKey** — `find(int $id)`
* **DatastoreHandlerHasWhere** — `where()` returning a query builder
* **DatastoreHandlerHasCounts** — `count(array $args)`

Plus automatic:
* Query building with escaping
* Result caching with invalidation
* Event broadcasting on mutations
* Table schema management

---

## Basic Usage

```php
<?php

namespace App\Service\Datastores\Post;

use PHPNomad\Database\Abstracts\IdentifiableDatabaseDatastoreHandler;
use PHPNomad\Database\Providers\DatabaseServiceProvider;
use PHPNomad\Database\Services\TableSchemaService;
use PHPNomad\Database\Traits\WithDatastoreHandlerMethods;
use App\Core\Datastores\Post\Interfaces\PostDatastoreHandler;
use App\Core\Models\Adapters\PostAdapter;
use App\Core\Models\Post;

class PostHandler extends IdentifiableDatabaseDatastoreHandler implements PostDatastoreHandler
{
    use WithDatastoreHandlerMethods;

    public function __construct(
        DatabaseServiceProvider $serviceProvider,
        PostsTable $table,
        PostAdapter $adapter,
        TableSchemaService $tableSchemaService
    ) {
        $this->model = Post::class;
        $this->table = $table;
        $this->modelAdapter = $adapter;
        $this->serviceProvider = $serviceProvider;
        $this->tableSchemaService = $tableSchemaService;
    }
}
```

That's it! This handler now supports:
- `find($id)` - Find by primary key
- `get($args)` - Get multiple records
- `save($model)` - Create or update
- `delete($model)` - Remove record
- `where()` - Query builder
- `count($args)` - Count records

---

## Required Properties

You must set these five properties in your constructor:

### `$model`
The model class name this handler works with.

```php
$this->model = Post::class;
```

### `$table`
The table definition for database schema.

```php
$this->table = $table;
```

### `$modelAdapter`
The adapter for converting between models and arrays.

```php
$this->modelAdapter = $adapter;
```

### `$serviceProvider`
The database service provider with query builders, cache, events.

```php
$this->serviceProvider = $serviceProvider;
```

### `$tableSchemaService`
Service for managing table creation and updates.

```php
$this->tableSchemaService = $tableSchemaService;
```

---

## Provided Methods

### `find(int $id): Model`

Finds a single record by primary key.

**Implementation:**
- Checks cache first
- On cache miss, executes SELECT query
- Converts result to model via adapter
- Stores in cache
- Returns model

**Throws:** `RecordNotFoundException` if not found.

**Example:**
```php
$post = $handler->find(42);
```

---

### `get(array $args = []): iterable`

Retrieves multiple records matching criteria.

**Implementation:**
- Builds WHERE clause from `$args`
- Executes SELECT query
- Converts each row to model
- Returns iterable collection

**Example:**
```php
$posts = $handler->get(['author_id' => 123, 'status' => 'published']);
```

---

### `save(Model $item): Model`

Creates or updates a record.

**Implementation:**
- Converts model to array via adapter
- Determines INSERT or UPDATE based on primary key
- Executes query
- Invalidates cache
- Broadcasts `RecordCreated` or `RecordUpdated` event
- Returns saved model with generated ID (if new)

**Example:**
```php
$newPost = new Post(null, 'Title', 'Content', 123, new DateTime());
$savedPost = $handler->save($newPost);
echo $savedPost->id;  // Now has an ID
```

---

### `delete(Model $item): void`

Removes a record from the database.

**Implementation:**
- Extracts primary key from model
- Executes DELETE query
- Invalidates cache
- Broadcasts `RecordDeleted` event

**Example:**
```php
$post = $handler->find(42);
$handler->delete($post);
```

---

### `where(): DatastoreWhereQuery`

Returns a query builder for complex filtering.

**Returns:** Query interface with methods like `equals()`, `greaterThan()`, `orderBy()`, `limit()`.

**Example:**
```php
$posts = $handler
    ->where()
    ->equals('author_id', 123)
    ->greaterThan('view_count', 100)
    ->orderBy('published_date', 'DESC')
    ->limit(10)
    ->getResults();
```

---

### `count(array $args = []): int`

Counts records matching criteria.

**Implementation:**
- Builds WHERE clause from `$args`
- Executes SELECT COUNT(*) query
- Returns integer count

**Example:**
```php
$publishedCount = $handler->count(['status' => 'published']);
```

---

## Custom Methods

You can add custom business methods alongside the standard ones:

```php
class PostHandler extends IdentifiableDatabaseDatastoreHandler
{
    // ... standard setup ...

    /**
     * Custom method: find posts by slug
     */
    public function findBySlug(string $slug): ?Post
    {
        $clause = $this->serviceProvider->clauseBuilder
            ->useTable($this->table)
            ->where('slug', '=', $slug);

        $sql = $this->serviceProvider->queryBuilder
            ->select('*')
            ->from($this->table)
            ->where($clause)
            ->build();

        $row = $this->serviceProvider->queryStrategy->querySingle($sql);
        
        return $row ? $this->modelAdapter->toModel($row) : null;
    }

    /**
     * Custom method: get top posts by view count
     */
    public function getTopPosts(int $limit = 10): iterable
    {
        $sql = $this->serviceProvider->queryBuilder
            ->select('*')
            ->from($this->table)
            ->orderBy('view_count', 'DESC')
            ->limit($limit)
            ->build();

        $rows = $this->serviceProvider->queryStrategy->query($sql);
        
        return array_map(
            fn($row) => $this->modelAdapter->toModel($row),
            $rows
        );
    }
}
```

---

## Caching Behavior

All read operations are automatically cached:

**Cached operations:**
- `find()` — cached by ID
- `get()` — cached by args hash
- `where()` results — cached by query hash

**Cache invalidation:**
- `save()` — clears cache for that record
- `delete()` — clears cache for that record
- Both also clear list caches

**Customize caching:**
```php
// Override to customize cache behavior
protected function getCacheKey(array $context): string
{
    return 'posts:' . md5(serialize($context));
}

protected function shouldCache(string $operation, array $context): bool
{
    // Don't cache queries with LIMIT > 100
    return !isset($context['limit']) || $context['limit'] <= 100;
}
```

---

## Event Broadcasting

Mutations automatically broadcast events:

**Events:**
- `RecordCreated` — after successful INSERT
- `RecordUpdated` — after successful UPDATE
- `RecordDeleted` — after successful DELETE

**Customize events:**
```php
// Override to add custom events
public function save(Model $item): Model
{
    $isNew = !$item->getId();
    $result = parent::save($item);
    
    // Custom event
    if ($isNew && $item->status === 'published') {
        $this->serviceProvider->eventStrategy->broadcast(
            new PostPublished($result)
        );
    }
    
    return $result;
}
```

---

## Table Schema Management

The handler ensures the table exists on first use:

**Automatic table creation:**
- Checks if table exists
- Creates table if missing
- Updates table if schema version changed
- All transparent to your code

**Table versioning:**
When you increment `$table->getTableVersion()`, the handler detects changes and updates the schema.

---

## Error Handling

The base handler includes error handling:

**Exceptions thrown:**
- `RecordNotFoundException` — `find()` with invalid ID
- `QueryBuilderException` — malformed queries
- `DatabaseException` — connection/query errors

**Logging:**
All errors are logged via `LoggerStrategy`:

```php
try {
    $post = $handler->find($id);
} catch (RecordNotFoundException $e) {
    // Logged automatically: "Record not found: posts:123"
}
```

---

## Best Practices

### Use WithDatastoreHandlerMethods Trait

```php
// ✅ GOOD: use trait for standard implementations
class PostHandler extends IdentifiableDatabaseDatastoreHandler
{
    use WithDatastoreHandlerMethods;
}

// ❌ BAD: implement methods manually
class PostHandler extends IdentifiableDatabaseDatastoreHandler
{
    public function find($id) { /* manual implementation */ }
}
```

### Set All Required Properties

```php
// ✅ GOOD: all properties set
public function __construct(...)
{
    $this->model = Post::class;
    $this->table = $table;
    $this->modelAdapter = $adapter;
    $this->serviceProvider = $serviceProvider;
    $this->tableSchemaService = $tableSchemaService;
}

// ❌ BAD: missing properties
public function __construct(...)
{
    $this->table = $table;
    // Missing model, adapter, etc.
}
```

### Keep Handlers Focused on Persistence

```php
// ✅ GOOD: handler does storage only
public function save(Model $item): Model
{
    return parent::save($item);
}

// ❌ BAD: handler contains business logic
public function save(Model $item): Model
{
    if ($item->publishedDate < new DateTime()) {
        throw new ValidationException("Cannot publish in the past");
    }
    return parent::save($item);
}
```

Business logic belongs in services, not handlers.

---

## What's Next

* [WithDatastoreHandlerMethods](/packages/database/handlers/with-datastore-handler-methods) — the trait that powers this base class
* [Database Handlers Introduction](/packages/database/handlers/introduction) — overview of handler architecture
* [Query Building](/packages/database/query-building) — building custom queries
* [Caching and Events](/packages/database/caching-and-events) — customizing cache and event behavior
* [Logger Package](/packages/logger/introduction) — LoggerStrategy interface for error logging
