# WithDatastoreHandlerMethods Trait

The `WithDatastoreHandlerMethods` trait provides the **actual implementation** of all standard datastore handler methods. When you extend `IdentifiableDatabaseDatastoreHandler` and use this trait, you get complete CRUD functionality with zero boilerplate.

This trait is the workhorse that powers database handlers—it contains all the query building, caching, event broadcasting, and data conversion logic.

## What It Provides

The trait implements:

* **CRUD operations** — `find()`, `get()`, `save()`, `delete()`
* **Query building** — constructs SQL queries with proper escaping
* **WHERE clause support** — `where()` method returning query builder
* **Counting** — `count()` method for efficient record counting
* **Automatic caching** — caches reads, invalidates on writes
* **Event broadcasting** — fires events on mutations
* **Error handling** — catches and logs database errors

## Basic Usage

```php
<?php

use PHPNomad\Database\Abstracts\IdentifiableDatabaseDatastoreHandler;
use PHPNomad\Database\Traits\WithDatastoreHandlerMethods;

class PostHandler extends IdentifiableDatabaseDatastoreHandler
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

That's all you need—the trait provides all CRUD methods automatically.

---

## Implemented Methods

### `find(int $id): Model`

**What it does:**
1. Generates cache key from ID
2. Checks cache for existing record
3. On cache miss:
   - Builds SELECT query with WHERE id = ?
   - Executes query via QueryStrategy
   - Converts result row to model via adapter
   - Stores in cache
4. Returns model

**Throws:** `RecordNotFoundException` if ID doesn't exist

**Generated SQL:**
```sql
SELECT * FROM wp_posts WHERE id = 123
```

---

### `get(array $args = []): iterable`

**What it does:**
1. Builds WHERE clause from `$args` array
2. Constructs SELECT query
3. Executes query
4. Converts each row to model via adapter
5. Returns iterable collection

**Example args:**
```php
$args = [
    'author_id' => 123,
    'status' => 'published',
    'limit' => 10,
    'offset' => 20
];
```

**Generated SQL:**
```sql
SELECT * FROM wp_posts 
WHERE author_id = 123 AND status = 'published'
LIMIT 10 OFFSET 20
```

---

### `save(Model $item): Model`

**What it does:**
1. Converts model to array via adapter
2. Checks if model has primary key:
   - **If NO key** → INSERT new record
   - **If HAS key** → UPDATE existing record
3. Executes query via QueryStrategy
4. Invalidates cache for this record
5. Broadcasts event:
   - `RecordCreated` for INSERT
   - `RecordUpdated` for UPDATE
6. Returns model with ID populated

**INSERT SQL:**
```sql
INSERT INTO wp_posts (title, content, author_id, published_date)
VALUES ('Title', 'Content', 123, '2024-01-01 12:00:00')
```

**UPDATE SQL:**
```sql
UPDATE wp_posts 
SET title = 'New Title', content = 'New Content'
WHERE id = 123
```

---

### `delete(Model $item): void`

**What it does:**
1. Extracts primary key from model
2. Builds DELETE query
3. Executes query
4. Invalidates cache for this record
5. Broadcasts `RecordDeleted` event

**Generated SQL:**
```sql
DELETE FROM wp_posts WHERE id = 123
```

---

### `where(): DatastoreWhereQuery`

**What it does:**
Returns a query builder instance configured for the handler's table.

**Returns:** Object with fluent API:
- `equals(field, value)`
- `greaterThan(field, value)`
- `lessThan(field, value)`
- `in(field, ...values)`
- `like(field, pattern)`
- `orderBy(field, direction)`
- `limit(count)`
- `offset(count)`
- `getResults()`

**Example:**
```php
$posts = $handler
    ->where()
    ->equals('status', 'published')
    ->greaterThan('view_count', 100)
    ->orderBy('published_date', 'DESC')
    ->limit(10)
    ->getResults();
```

---

### `count(array $args = []): int`

**What it does:**
1. Builds WHERE clause from `$args`
2. Constructs SELECT COUNT(*) query
3. Executes query
4. Returns integer count

**Generated SQL:**
```sql
SELECT COUNT(*) FROM wp_posts 
WHERE status = 'published'
```

---

## Caching Implementation

The trait integrates with `CacheableService`:

### Cache Keys

**Single record:**
```php
// Cache key: posts:123
$cacheKey = $this->table->getTableName() . ':' . $id;
```

**List queries:**
```php
// Cache key: posts:list:md5(serialize($args))
$cacheKey = $this->table->getTableName() . ':list:' . md5(serialize($args));
```

### Cache Invalidation

On `save()` or `delete()`:
```php
// Clear single record cache
$this->serviceProvider->cacheableService->forget(['id' => $id]);

// Clear all list caches for this table
$this->serviceProvider->cacheableService->forgetMatching(
    $this->table->getTableName() . ':list:*'
);
```

---

## Event Broadcasting

The trait broadcasts standard events:

### RecordCreated

Fired after successful INSERT:

```php
$this->serviceProvider->eventStrategy->broadcast(
    new RecordCreated(
        table: $this->table->getTableName(),
        model: $savedModel
    )
);
```

### RecordUpdated

Fired after successful UPDATE:

```php
$this->serviceProvider->eventStrategy->broadcast(
    new RecordUpdated(
        table: $this->table->getTableName(),
        model: $savedModel
    )
);
```

### RecordDeleted

Fired after successful DELETE:

```php
$this->serviceProvider->eventStrategy->broadcast(
    new RecordDeleted(
        table: $this->table->getTableName(),
        model: $deletedModel
    )
);
```

---

## Query Building Implementation

The trait uses `QueryBuilder` and `ClauseBuilder` from the service provider:

### Building SELECT Queries

```php
protected function buildSelectQuery(array $args): string
{
    $clause = $this->serviceProvider->clauseBuilder
        ->useTable($this->table);

    // Add WHERE conditions from args
    foreach ($args as $field => $value) {
        if ($field === 'limit' || $field === 'offset') {
            continue;  // Handle separately
        }
        $clause->where($field, '=', $value);
    }

    $query = $this->serviceProvider->queryBuilder
        ->select('*')
        ->from($this->table)
        ->where($clause);

    // Handle pagination
    if (isset($args['limit'])) {
        $query->limit($args['limit']);
    }
    if (isset($args['offset'])) {
        $query->offset($args['offset']);
    }

    return $query->build();
}
```

---

## Error Handling

The trait includes comprehensive error handling:

### RecordNotFoundException

Thrown when `find()` doesn't locate a record:

```php
if (!$row) {
    throw new RecordNotFoundException(
        "Record not found: {$this->table->getTableName()}:{$id}"
    );
}
```

### Database Errors

Caught and logged:

```php
try {
    $result = $this->serviceProvider->queryStrategy->execute($sql);
} catch (DatabaseException $e) {
    $this->serviceProvider->loggerStrategy->error(
        'Database query failed',
        [
            'table' => $this->table->getTableName(),
            'query' => $sql,
            'error' => $e->getMessage()
        ]
    );
    throw $e;
}
```

---

## Overriding Trait Methods

You can override any trait method to customize behavior:

### Override `save()` with Custom Logic

```php
class PostHandler extends IdentifiableDatabaseDatastoreHandler
{
    use WithDatastoreHandlerMethods {
        save as private traitSave;  // Rename trait method
    }

    public function save(Model $item): Model
    {
        // Custom pre-save logic
        $this->validatePost($item);
        
        // Call trait's save
        $result = $this->traitSave($item);
        
        // Custom post-save logic
        $this->updateSearchIndex($result);
        
        return $result;
    }

    private function validatePost(Model $post): void
    {
        if (empty($post->title)) {
            throw new ValidationException('Title required');
        }
    }
}
```

### Override `find()` with Custom Caching

```php
class PostHandler extends IdentifiableDatabaseDatastoreHandler
{
    use WithDatastoreHandlerMethods;

    public function find(int $id): Model
    {
        // Custom cache key
        $cacheKey = "posts:full:{$id}";
        
        return $this->serviceProvider->cacheableService->getWithCache(
            operation: 'find',
            context: ['key' => $cacheKey],
            callback: function() use ($id) {
                // Execute query
                $sql = $this->buildFindQuery($id);
                $row = $this->serviceProvider->queryStrategy->querySingle($sql);
                
                if (!$row) {
                    throw new RecordNotFoundException("Post {$id} not found");
                }
                
                return $this->modelAdapter->toModel($row);
            }
        );
    }
}
```

---

## Required Properties

The trait expects these properties to be set by your handler:

```php
protected string $model;                      // Model class name
protected Table $table;                       // Table definition
protected ModelAdapter $modelAdapter;         // Model adapter
protected DatabaseServiceProvider $serviceProvider;  // Service provider
protected TableSchemaService $tableSchemaService;    // Schema service
```

If any are missing, trait methods will fail with errors.

---

## Best Practices

### Always Use the Trait

```php
// ✅ GOOD: use trait
class PostHandler extends IdentifiableDatabaseDatastoreHandler
{
    use WithDatastoreHandlerMethods;
}

// ❌ BAD: manual implementation
class PostHandler extends IdentifiableDatabaseDatastoreHandler
{
    public function find(int $id): Model
    {
        // Reimplementing what the trait already does
    }
}
```

### Override Only When Necessary

```php
// ✅ GOOD: override for specific needs
public function save(Model $item): Model
{
    $this->logSaveAttempt($item);
    return $this->traitSave($item);
}

// ❌ BAD: override without adding value
public function save(Model $item): Model
{
    return $this->traitSave($item);  // No customization
}
```

### Use Proper Method Renaming

```php
// ✅ GOOD: rename trait method to avoid conflicts
use WithDatastoreHandlerMethods {
    save as private traitSave;
}

public function save(Model $item): Model
{
    return $this->traitSave($item);
}

// ❌ BAD: call parent (doesn't work with traits)
public function save(Model $item): Model
{
    return parent::save($item);  // Error!
}
```

---

## What's Next

* [IdentifiableDatabaseDatastoreHandler](/packages/database/handlers/identifiable-database-datastore-handler) — the base class that uses this trait
* [Database Handlers Introduction](/packages/database/handlers/introduction) — handler architecture overview
* [Query Building](/packages/database/query-building) — understanding query construction
* [Caching and Events](/packages/database/caching-and-events) — how caching and events work
