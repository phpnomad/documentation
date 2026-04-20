# Datastore Integration Guide

This guide shows you how to integrate the datastore package into your application by creating a complete datastore implementation from scratch. While the [Getting Started Tutorial](/core-concepts/getting-started-tutorial) walks through the basics, this guide covers **production patterns**, **dependency injection setup**, and **custom implementations** for different storage backends.

## Integration Overview

Integrating a datastore involves four steps:

1. **Define your Core contracts** — interfaces for your datastore and handler
2. **Implement the Core datastore** — the public API layer
3. **Implement the Service handler** — the storage backend layer
4. **Register with DI** — wire everything together

This guide demonstrates each step using a `Post` entity as an example.

---

## Step 1: Define Core Contracts

Start by defining **interfaces** for your datastore and handler in the Core layer. These are the contracts your application depends on.

### Datastore Interface

```php
<?php

namespace App\Core\Datastores\Post\Interfaces;

use PHPNomad\Datastore\Interfaces\Datastore;
use PHPNomad\Datastore\Interfaces\DatastoreHasPrimaryKey;
use PHPNomad\Datastore\Interfaces\DatastoreHasWhere;
use PHPNomad\Datastore\Interfaces\DatastoreHasCounts;

interface PostDatastore extends 
    Datastore,
    DatastoreHasPrimaryKey,
    DatastoreHasWhere,
    DatastoreHasCounts
{
    // Optionally add custom business methods
    public function findPublishedPosts(int $authorId): iterable;
}
```

This interface:
- Extends standard PHPNomad interfaces for basic operations
- Defines what operations your application can perform
- Lives in `Core/` (business layer, not tied to storage)

### Handler Interface

```php
<?php

namespace App\Core\Datastores\Post\Interfaces;

use PHPNomad\Datastore\Interfaces\DatastoreHandler;
use PHPNomad\Datastore\Interfaces\DatastoreHandlerHasPrimaryKey;
use PHPNomad\Datastore\Interfaces\DatastoreHandlerHasWhere;
use PHPNomad\Datastore\Interfaces\DatastoreHandlerHasCounts;

interface PostDatastoreHandler extends 
    DatastoreHandler,
    DatastoreHandlerHasPrimaryKey,
    DatastoreHandlerHasWhere,
    DatastoreHandlerHasCounts
{
    // Handler contracts mirror datastore capabilities
}
```

---

## Step 2: Implement Core Datastore

The Core datastore implements your public interface and delegates to the handler. Use **decorator traits** to eliminate boilerplate.

```php
<?php

namespace App\Core\Datastores\Post;

use PHPNomad\Datastore\Traits\WithDatastorePrimaryKeyDecorator;
use PHPNomad\Datastore\Traits\WithDatastoreWhereDecorator;
use PHPNomad\Datastore\Traits\WithDatastoreCountDecorator;
use App\Core\Datastores\Post\Interfaces\PostDatastore as IPostDatastore;
use App\Core\Datastores\Post\Interfaces\PostDatastoreHandler;

class PostDatastore implements IPostDatastore
{
    use WithDatastorePrimaryKeyDecorator;  // get(), save(), delete(), find()
    use WithDatastoreWhereDecorator {      // where()
        WithDatastorePrimaryKeyDecorator::get insteadof WithDatastoreWhereDecorator;
        WithDatastorePrimaryKeyDecorator::save insteadof WithDatastoreWhereDecorator;
        WithDatastorePrimaryKeyDecorator::delete insteadof WithDatastoreWhereDecorator;
    }
    use WithDatastoreCountDecorator;       // count()

    public function __construct(
        private PostDatastoreHandler $handler
    ) {}

    // Implement custom business method
    public function findPublishedPosts(int $authorId): iterable
    {
        return $this->handler
            ->where()
            ->equals('author_id', $authorId)
            ->lessThanOrEqual('published_date', new \DateTime())
            ->orderBy('published_date', 'DESC')
            ->getResults();
    }
}
```

**Key points:**
- Traits provide standard method implementations
- Trait conflicts are resolved with `insteadof`
- Custom methods are implemented manually
- Handler is injected via constructor

---

## Step 3: Implement Service Handler

The Service handler connects your datastore to actual storage. For database-backed datastores, extend `IdentifiableDatabaseDatastoreHandler`.

### Database Handler

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

class PostDatabaseHandler extends IdentifiableDatabaseDatastoreHandler implements PostDatastoreHandler
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

**Required components:**
- `DatabaseServiceProvider` — provides query builder, cache, events
- `Table` — schema definition for the database table
- `ModelAdapter` — converts between models and arrays
- `TableSchemaService` — handles schema creation/updates

### Table Definition

```php
<?php

namespace App\Service\Datastores\Post;

use PHPNomad\Database\Abstracts\Table;
use PHPNomad\Database\Factories\Column;
use PHPNomad\Database\Factories\Columns\PrimaryKeyFactory;
use PHPNomad\Database\Factories\Columns\DateCreatedFactory;
use PHPNomad\Database\Factories\Columns\DateModifiedFactory;

class PostsTable extends Table
{
    public function getAlias(): string
    {
        return 'posts';
    }

    public function getTableVersion(): string
    {
        return '1';
    }

    public function getColumns(): array
    {
        return [
            (new PrimaryKeyFactory())->toColumn(),
            new Column('title', 'VARCHAR', [255], 'NOT NULL'),
            new Column('content', 'TEXT', null, 'NOT NULL'),
            new Column('author_id', 'BIGINT', null, 'NOT NULL'),
            new Column('published_date', 'DATETIME', null, 'NULL'),
            (new DateCreatedFactory())->toColumn(),
            (new DateModifiedFactory())->toColumn(),
        ];
    }

    public function getIndices(): array
    {
        return [
            new Index(['author_id'], 'author_idx', 'INDEX'),
            new Index(['published_date'], 'published_idx', 'INDEX'),
        ];
    }

    public function getUnprefixedName(): string
    {
        return 'posts';
    }

    public function getSingularUnprefixedName(): string
    {
        return 'post';
    }
}
```

---

## Step 4: Register with Dependency Injection

Wire everything together in your service provider:

```php
<?php

namespace App\Service\Providers;

use PHPNomad\Di\Interfaces\CanSet;
use App\Core\Datastores\Post\Interfaces\PostDatastore;
use App\Core\Datastores\Post\Interfaces\PostDatastoreHandler;
use App\Core\Datastores\Post\PostDatastore as CorePostDatastore;
use App\Service\Datastores\Post\PostDatabaseHandler;
use App\Core\Models\Adapters\PostAdapter;
use App\Service\Datastores\Post\PostsTable;

class PostServiceProvider
{
    public function register(CanSet $container): void
    {
        // Register the adapter
        $container->set(PostAdapter::class, fn() => new PostAdapter());

        // Register the table
        $container->set(PostsTable::class, fn() => new PostsTable());

        // Register the handler (Service layer)
        $container->set(PostDatastoreHandler::class, function($c) {
            return new PostDatabaseHandler(
                $c->get(DatabaseServiceProvider::class),
                $c->get(PostsTable::class),
                $c->get(PostAdapter::class),
                $c->get(TableSchemaService::class)
            );
        });

        // Register the datastore (Core layer)
        $container->set(PostDatastore::class, function($c) {
            return new CorePostDatastore(
                $c->get(PostDatastoreHandler::class)
            );
        });
    }
}
```

Now your application can inject `PostDatastore` anywhere it needs data access:

```php
class PublishPostService
{
    public function __construct(
        private PostDatastore $posts
    ) {}

    public function publish(int $postId): void
    {
        $post = $this->posts->find($postId);
        // ... business logic
    }
}
```

---

## Alternative Backend: REST API Handler

You can implement handlers for different storage backends. Here's a REST API example:

```php
<?php

namespace App\Service\Datastores\Post;

use PHPNomad\Http\Interfaces\Client;
use App\Core\Datastores\Post\Interfaces\PostDatastoreHandler;
use App\Core\Models\Adapters\PostAdapter;
use App\Core\Models\Post;

class PostRestHandler implements PostDatastoreHandler
{
    public function __construct(
        private Client $httpClient,
        private PostAdapter $adapter,
        private string $apiBaseUrl
    ) {}

    public function find(int $id): Post
    {
        $response = $this->httpClient->get("{$this->apiBaseUrl}/posts/{$id}");
        
        if ($response->getStatusCode() === 404) {
            throw new RecordNotFoundException("Post {$id} not found");
        }

        $data = json_decode($response->getBody(), true);
        return $this->adapter->toModel($data);
    }

    public function get(array $args = []): iterable
    {
        $queryString = http_build_query($args);
        $response = $this->httpClient->get("{$this->apiBaseUrl}/posts?{$queryString}");
        $data = json_decode($response->getBody(), true);

        return array_map(
            fn($item) => $this->adapter->toModel($item),
            $data['posts'] ?? []
        );
    }

    public function save(Post $item): Post
    {
        $data = $this->adapter->toArray($item);
        
        if ($item->getId()) {
            // UPDATE
            $response = $this->httpClient->put(
                "{$this->apiBaseUrl}/posts/{$item->getId()}",
                $data
            );
        } else {
            // CREATE
            $response = $this->httpClient->post(
                "{$this->apiBaseUrl}/posts",
                $data
            );
        }

        $responseData = json_decode($response->getBody(), true);
        return $this->adapter->toModel($responseData);
    }

    public function delete(Post $item): void
    {
        $this->httpClient->delete("{$this->apiBaseUrl}/posts/{$item->getId()}");
    }

    public function where(): DatastoreWhereQuery
    {
        // Return a REST-compatible query builder
        return new RestWhereQuery($this->httpClient, $this->apiBaseUrl, $this->adapter);
    }

    public function count(array $args = []): int
    {
        $queryString = http_build_query(array_merge($args, ['count_only' => true]));
        $response = $this->httpClient->get("{$this->apiBaseUrl}/posts?{$queryString}");
        $data = json_decode($response->getBody(), true);
        return $data['count'] ?? 0;
    }
}
```

**Register the REST handler instead:**

```php
$container->set(PostDatastoreHandler::class, function($c) {
    return new PostRestHandler(
        $c->get(Client::class),
        $c->get(PostAdapter::class),
        'https://api.example.com/v1'
    );
});
```

Your Core datastore and application code **don't change**—only the handler implementation.

---

## Best Practices

### Keep Core and Service Separate

```
Core/
  Datastores/
    Post/
      Interfaces/
        PostDatastore.php          # Public interface
        PostDatastoreHandler.php   # Handler contract
      PostDatastore.php            # Implementation (delegates to handler)
  Models/
    Post.php
    Adapters/
      PostAdapter.php

Service/
  Datastores/
    Post/
      PostDatabaseHandler.php      # Database implementation
      PostsTable.php               # Schema definition
```

**Core** = business logic, storage-agnostic  
**Service** = concrete storage implementations

### Use Traits for Standard Implementations

Don't write boilerplate delegation code:

```php
// ❌ BAD: manual delegation
class PostDatastore implements IPostDatastore
{
    public function get(array $args = []): iterable
    {
        return $this->handler->get($args);
    }
    
    public function save(Model $item): Model
    {
        return $this->handler->save($item);
    }
    
    // ... etc
}

// ✅ GOOD: use traits
class PostDatastore implements IPostDatastore
{
    use WithDatastorePrimaryKeyDecorator;
    use WithDatastoreWhereDecorator;
    use WithDatastoreCountDecorator;
}
```

### Inject Interfaces, Not Implementations

```php
// ✅ GOOD: depend on interface
class PostService
{
    public function __construct(
        private PostDatastore $posts  // Interface
    ) {}
}

// ❌ BAD: depend on implementation
class PostService
{
    public function __construct(
        private CorePostDatastore $posts  // Concrete class
    ) {}
}
```

This allows swapping implementations (database → REST) without touching consumers.

---

## What's Next

* [Model Adapters](/packages/datastore/model-adapters) — converting between models and storage arrays
* [Database Handlers](/packages/database/handlers/introduction) — database-specific handler details
* [Table Definitions](/packages/database/tables/introduction) — defining database schemas
* [Core Implementation](/packages/datastore/core-implementation) — advanced Core datastore patterns
