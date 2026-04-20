# DatabaseServiceProvider

The `DatabaseServiceProvider` is a **dependency container** that provides all the services database handlers need to function. It bundles query builders, cache services, event broadcasting, and logging into a single injectable dependency, simplifying handler construction.

Instead of injecting 5-6 separate dependencies into every handler, you inject one `DatabaseServiceProvider` and access its public properties.

## What It Provides

The `DatabaseServiceProvider` class exposes six services:

```php
class DatabaseServiceProvider
{
    public LoggerStrategy $loggerStrategy;
    public QueryStrategy $queryStrategy;
    public CacheableService $cacheableService;
    public QueryBuilder $queryBuilder;
    public ClauseBuilder $clauseBuilder;
    public EventStrategy $eventStrategy;
}
```

These services are injected into the provider via its constructor and made available as public properties.

---

## Services Overview

### 1. QueryBuilder

Builds safe, escaped SQL SELECT queries.

**Usage:**
```php
$sql = $serviceProvider->queryBuilder
    ->select('*')
    ->from($table)
    ->where($clause)
    ->limit(10)
    ->build();
```

**See:** [Query Building](/packages/database/query-building)

---

### 2. ClauseBuilder

Constructs WHERE clauses for queries.

**Usage:**
```php
$clause = $serviceProvider->clauseBuilder
    ->useTable($table)
    ->where('author_id', '=', 123)
    ->andWhere('status', '=', 'published');
```

**See:** [Query Building](/packages/database/query-building)

---

### 3. QueryStrategy

Executes SQL queries against the database.

**Usage:**
```php
// Execute query and return results
$rows = $serviceProvider->queryStrategy->query($sql);

// Execute query and return single row
$row = $serviceProvider->queryStrategy->querySingle($sql);

// Execute mutation (INSERT, UPDATE, DELETE)
$affected = $serviceProvider->queryStrategy->execute($sql);
```

---

### 4. CacheableService

Provides automatic caching for query results.

**Usage:**
```php
$post = $serviceProvider->cacheableService->getWithCache(
    operation: 'find',
    context: ['id' => 123],
    callback: fn() => $this->executeQuery("SELECT * FROM posts WHERE id = 123")
);
```

**See:** [Caching and Events](/packages/database/caching-and-events)

---

### 5. EventStrategy

Broadcasts events to registered listeners.

**Usage:**
```php
$serviceProvider->eventStrategy->broadcast(
    new RecordCreated('posts', $post)
);
```

**See:** [Caching and Events](/packages/database/caching-and-events)

---

### 6. LoggerStrategy

Logs errors, warnings, and debug information.

**Usage:**
```php
$serviceProvider->loggerStrategy->error('Database query failed', [
    'query' => $sql,
    'error' => $exception->getMessage()
]);

$serviceProvider->loggerStrategy->debug('Query executed', [
    'query' => $sql,
    'duration' => $duration
]);
```

---

## Using DatabaseServiceProvider in Handlers

Handlers receive the provider via constructor injection:

```php
<?php

use PHPNomad\Database\Abstracts\IdentifiableDatabaseDatastoreHandler;
use PHPNomad\Database\Providers\DatabaseServiceProvider;

class PostHandler extends IdentifiableDatabaseDatastoreHandler
{
    private QueryBuilder $queryBuilder;
    private ClauseBuilder $clauseBuilder;
    private CacheableService $cache;
    private EventStrategy $events;
    private LoggerStrategy $logger;

    public function __construct(
        DatabaseServiceProvider $serviceProvider,
        PostsTable $table,
        PostAdapter $adapter,
        TableSchemaService $tableSchemaService
    ) {
        // Extract services from provider
        $this->queryBuilder = $serviceProvider->queryBuilder;
        $this->clauseBuilder = $serviceProvider->clauseBuilder;
        $this->cache = $serviceProvider->cacheableService;
        $this->events = $serviceProvider->eventStrategy;
        $this->logger = $serviceProvider->loggerStrategy;

        // Set handler properties
        $this->table = $table;
        $this->modelAdapter = $adapter;
        $this->serviceProvider = $serviceProvider;
        $this->tableSchemaService = $tableSchemaService;
    }

    public function findPublished(): array
    {
        try {
            return $this->cache->getWithCache(
                'list:published',
                [],
                function() {
                    $clause = $this->clauseBuilder
                        ->useTable($this->table)
                        ->where('status', '=', 'published');

                    $sql = $this->queryBuilder
                        ->select('*')
                        ->from($this->table)
                        ->where($clause)
                        ->build();

                    $rows = $this->serviceProvider->queryStrategy->query($sql);

                    return array_map(
                        fn($row) => $this->modelAdapter->toModel($row),
                        $rows
                    );
                }
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch published posts', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
```

---

## Why Use a Service Provider?

### Without Service Provider

Every handler would need 6+ constructor parameters:

```php
public function __construct(
    QueryBuilder $queryBuilder,
    ClauseBuilder $clauseBuilder,
    QueryStrategy $queryStrategy,
    CacheableService $cacheableService,
    EventStrategy $eventStrategy,
    LoggerStrategy $loggerStrategy,
    PostsTable $table,
    PostAdapter $adapter,
    TableSchemaService $tableSchemaService
) {
    // 9 dependencies!
}
```

### With Service Provider

Only 4 constructor parameters:

```php
public function __construct(
    DatabaseServiceProvider $serviceProvider,
    PostsTable $table,
    PostAdapter $adapter,
    TableSchemaService $tableSchemaService
) {
    // 4 dependencies - much cleaner
}
```

---

## Registering DatabaseServiceProvider

The provider is registered once in your DI container:

```php
<?php

use PHPNomad\Di\Interfaces\CanSet;
use PHPNomad\Database\Providers\DatabaseServiceProvider;
use PHPNomad\Database\Services\QueryBuilder;
use PHPNomad\Database\Services\ClauseBuilder;
use PHPNomad\Database\Services\QueryStrategy;
use PHPNomad\Cache\Services\CacheableService;
use PHPNomad\Events\Services\EventStrategy;
use PHPNomad\Logger\Services\LoggerStrategy;

class AppServiceProvider
{
    public function register(CanSet $container): void
    {
        // Register individual services
        $container->set(QueryBuilder::class, fn() => new QueryBuilder());
        $container->set(ClauseBuilder::class, fn() => new ClauseBuilder());
        $container->set(QueryStrategy::class, fn() => new MysqlQueryStrategy());
        $container->set(CacheableService::class, fn($c) => 
            new CacheableService(
                $c->get(EventStrategy::class),
                $c->get(CacheStrategy::class),
                $c->get(CachePolicy::class)
            )
        );
        $container->set(EventStrategy::class, fn() => new EventStrategy());
        $container->set(LoggerStrategy::class, fn() => new FileLogger());

        // Register provider that bundles them all
        $container->set(DatabaseServiceProvider::class, function($c) {
            return new DatabaseServiceProvider(
                loggerStrategy: $c->get(LoggerStrategy::class),
                queryStrategy: $c->get(QueryStrategy::class),
                queryBuilder: $c->get(QueryBuilder::class),
                clauseBuilder: $c->get(ClauseBuilder::class),
                cacheableService: $c->get(CacheableService::class),
                eventStrategy: $c->get(EventStrategy::class)
            );
        });
    }
}
```

Now every handler can inject `DatabaseServiceProvider` and access all services.

---

## Accessing Services

### Direct Access

```php
$queryBuilder = $serviceProvider->queryBuilder;
$cache = $serviceProvider->cacheableService;
```

### In Base Class (IdentifiableDatabaseDatastoreHandler)

The base handler stores the provider for internal use:

```php
abstract class IdentifiableDatabaseDatastoreHandler
{
    protected DatabaseServiceProvider $serviceProvider;

    protected function executeQuery(string $sql): array
    {
        return $this->serviceProvider->queryStrategy->query($sql);
    }

    protected function log(string $message, array $context = []): void
    {
        $this->serviceProvider->loggerStrategy->info($message, $context);
    }
}
```

Your handlers inherit these helper methods.

---

## Example: Complete Handler with Provider

```php
<?php

namespace App\Service\Datastores\Post;

use PHPNomad\Database\Abstracts\IdentifiableDatabaseDatastoreHandler;
use PHPNomad\Database\Providers\DatabaseServiceProvider;
use PHPNomad\Database\Services\TableSchemaService;
use App\Core\Models\Adapters\PostAdapter;

class PostHandler extends IdentifiableDatabaseDatastoreHandler
{
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

    // All standard methods (find, get, save, delete) are provided by base class
    // Base class uses $this->serviceProvider internally

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

        try {
            $row = $this->serviceProvider->queryStrategy->querySingle($sql);
            return $row ? $this->modelAdapter->toModel($row) : null;
        } catch (\Exception $e) {
            $this->serviceProvider->loggerStrategy->error('Failed to find post by slug', [
                'slug' => $slug,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
```

---

## Benefits

### 1. Simplified Constructor

Reduces constructor complexity from 9+ parameters to 4.

### 2. Consistent Service Access

All handlers access the same service instances, ensuring consistency.

### 3. Easy Mocking in Tests

Mock one provider instead of 6 individual services:

```php
$mockProvider = $this->createMock(DatabaseServiceProvider::class);
$mockProvider->queryBuilder = $this->createMock(QueryBuilder::class);
$mockProvider->cacheableService = $this->createMock(CacheableService::class);
// etc.

$handler = new PostHandler($mockProvider, $table, $adapter, $schemaService);
```

### 4. Centralized Configuration

Change implementations (e.g., swap MySQL for PostgreSQL) in one place:

```php
$container->set(QueryStrategy::class, fn() => new PostgresQueryStrategy());
// All handlers automatically use PostgreSQL
```

---

## Best Practices

### Extract Services in Constructor

```php
// ✅ GOOD: extract to properties
public function __construct(DatabaseServiceProvider $serviceProvider, ...)
{
    $this->queryBuilder = $serviceProvider->queryBuilder;
    $this->cache = $serviceProvider->cacheableService;
}

// ❌ BAD: access provider repeatedly
public function find($id) {
    $this->serviceProvider->queryBuilder->select(...);  // Verbose
}
```

### Don't Create Provider Manually

```php
// ❌ BAD: manual instantiation
$provider = new DatabaseServiceProvider(...);

// ✅ GOOD: inject from container
public function __construct(DatabaseServiceProvider $serviceProvider)
```

### Use Provider Properties, Not Methods

The provider exposes services as **public properties**, not methods:

```php
// ✅ GOOD: property access
$serviceProvider->queryBuilder

// ❌ BAD: no getter methods
$serviceProvider->getQueryBuilder()  // Doesn't exist
```

---

## What's Next

* [Database Handlers](/packages/database/handlers/introduction) — handlers that use the provider
* [Query Building](/packages/database/query-building) — using QueryBuilder and ClauseBuilder
* [Caching and Events](/packages/database/caching-and-events) — using CacheableService and EventStrategy
* [Logger Package](/packages/logger/introduction) — LoggerStrategy interface documentation
* [Event Package](/packages/event/introduction) — EventStrategy interface documentation
