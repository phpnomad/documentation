# Getting Started: Your First Datastore

This tutorial guides you through creating a complete database-backed datastore for a `Post` entity in PHPNomad.
You will build all the components required by the datastore pattern: the model, adapter, Core interfaces and
implementation, database table definition, database handler, and dependency injection registration.

By the end, you will have a working datastore that can create, read, update, delete, and query blog posts stored in a database.

---

## Prerequisites

Before starting, ensure you have:

- PHPNomad framework installed and configured
- A database configured and accessible (MySQL, MariaDB, or compatible)
- Basic understanding of PHP interfaces and dependency injection
- Familiarity with the [datastore architecture concepts](overview-and-architecture)

---

## Directory structure

Create the following directory structure for your Post datastore:

```
Blog/
├── Core/
│   ├── Models/
│   │   ├── Post.php
│   │   └── Adapters/
│   │       └── PostAdapter.php
│   └── Datastores/
│       └── Post/
│           ├── Interfaces/
│           │   ├── PostDatastore.php
│           │   └── PostDatastoreHandler.php
│           └── PostDatastore.php
└── Service/
    └── Datastores/
        └── Post/
            ├── PostDatabaseDatastoreHandler.php
            └── PostsTable.php
```

This structure separates Core (business logic) from Service (implementation details), which is fundamental to the
datastore pattern.

---

## Step 1: Define your model

Models represent domain entities as immutable value objects. They contain data and behavior but no persistence logic.

Create `Blog/Core/Models/Post.php`:

```php
<?php

namespace Blog\Core\Models;

use Nomad\Datastore\Interfaces\DataModel;
use Nomad\Datastore\Interfaces\HasSingleIntIdentity;
use Nomad\Datastore\Traits\WithSingleIntIdentity;
use Nomad\Datastore\Traits\WithCreatedDate;
use DateTime;

class Post implements DataModel, HasSingleIntIdentity
{
    use WithSingleIntIdentity;
    use WithCreatedDate;

    public function __construct(
        int $id,
        public readonly string $title,
        public readonly string $content,
        public readonly int $authorId,
        public readonly DateTime $publishedDate,
        ?DateTime $createdDate = null
    ) {
        $this->id = $id;
        $this->createdDate = $createdDate;
    }
}
```

**Key points**:
- `HasSingleIntIdentity` provides the `getId()` method and identity tracking
- `WithSingleIntIdentity` trait implements the identity interface
- `WithCreatedDate` provides automatic timestamp tracking
- Properties use `public readonly` for immutability and direct access
- `id` and `createdDate` are managed by traits, other properties use constructor promotion

For detailed information about model identity patterns and traits, see [Models and Identity](models-and-identity).

---

## Step 2: Create the model adapter

Adapters convert between models and storage representations (arrays). The database handler uses adapters to transform
database rows into models and vice versa.

Create `Blog/Core/Models/Adapters/PostAdapter.php`:

```php
<?php

namespace Blog\Core\Models\Adapters;

use Blog\Core\Models\Post;
use Nomad\Datastore\Interfaces\DataModel;
use Nomad\Datastore\Interfaces\ModelAdapter;
use Nomad\Utils\Helpers\Arr;
use Nomad\Date\Services\DateFormatterService;

class PostAdapter implements ModelAdapter
{
    public function __construct(
        protected DateFormatterService $dateFormatterService
    ) {}

    public function toModel(array $array): DataModel
    {
        return new Post(
            id: (int) Arr::get($array, 'id'),
            title: Arr::get($array, 'title'),
            content: Arr::get($array, 'content'),
            authorId: (int) Arr::get($array, 'authorId'),
            publishedDate: $this->dateFormatterService->getDateTime(
                Arr::get($array, 'publishedDate')
            ),
            createdDate: $this->dateFormatterService->getDateTimeOrNull(
                Arr::get($array, 'createdDate')
            )
        );
    }

    public function toArray(DataModel $model): array
    {
        /** @var Post $model */
        return [
            'id' => $model->getId(),
            'title' => $model->title,
            'content' => $model->content,
            'authorId' => $model->authorId,
            'publishedDate' => $this->dateFormatterService->getDateString(
                $model->publishedDate
            ),
            'createdDate' => $this->dateFormatterService->getDateStringOrNull(
                $model->getCreatedDate()
            ),
        ];
    }
}
```

**Key points**:

- `DateFormatterService` handles DateTime conversion to/from database format
- `Arr::get()` safely retrieves values from arrays
- `toModel()` converts database rows (arrays) to Post objects
- `toArray()` converts Post objects to database-compatible arrays
- Type casting ensures data integrity

---

## Step 3: Define Core datastore interfaces

Core interfaces declare what operations are possible without specifying how they work.

### PostDatastore interface

Create `Blog/Core/Datastores/Post/Interfaces/PostDatastore.php`:

```php
<?php

namespace Blog\Core\Datastores\Post\Interfaces;

use Blog\Core\Models\Post;
use Nomad\Datastore\Interfaces\Datastore;
use Nomad\Datastore\Interfaces\DatastoreHasPrimaryKey;
use Nomad\Datastore\Interfaces\DatastoreHasWhere;
use Nomad\Datastore\Interfaces\DatastoreHasCounts;

interface PostDatastore extends Datastore, DatastoreHasPrimaryKey, DatastoreHasWhere, DatastoreHasCounts
{
    /**
     * Get all published posts.
     *
     * @return Post[]
     */
    public function getPublishedPosts(): array;

    /**
     * Get posts by author.
     *
     * @param int $authorId
     * @return Post[]
     */
    public function getByAuthor(int $authorId): array;
}
```

**Key points**:
- Extends base datastore interfaces for standard CRUD operations
- Adds custom business methods specific to posts
- Documents return types for clarity

See [Datastore Interfaces](../packages/datastore/datastore-interfaces) for complete interface documentation.

### PostDatastoreHandler interface

Create `Blog/Core/Datastores/Post/Interfaces/PostDatastoreHandler.php`:

```php
<?php

namespace Blog\Core\Datastores\Post\Interfaces;

use Nomad\Datastore\Interfaces\Datastore;
use Nomad\Datastore\Interfaces\DatastoreHasPrimaryKey;
use Nomad\Datastore\Interfaces\DatastoreHasWhere;
use Nomad\Datastore\Interfaces\DatastoreHasCounts;

interface PostDatastoreHandler extends Datastore, DatastoreHasPrimaryKey, DatastoreHasWhere, DatastoreHasCounts
{
    // Handler only extends base interfaces, no custom methods
}
```

**Key points**:

- Extends the same base interfaces as PostDatastore
- Contains no custom business methods
- Serves as the contract for storage implementations
- This is the interface that database, REST, or GraphQL handlers will implement

---

## Step 4: Implement the Core datastore

The Core datastore implementation delegates standard operations to the handler and implements custom business logic.

Create `Blog/Core/Datastores/Post/PostDatastore.php`:

```php
<?php

namespace Blog\Core\Datastores\Post;

use Blog\Core\Datastores\Post\Interfaces\PostDatastore as PostDatastoreInterface;
use Blog\Core\Datastores\Post\Interfaces\PostDatastoreHandler;
use Blog\Core\Models\Post;
use Nomad\Datastore\Interfaces\Datastore;
use Nomad\Datastore\Traits\WithDatastoreDecorator;
use Nomad\Datastore\Traits\WithDatastorePrimaryKeyDecorator;
use Nomad\Datastore\Traits\WithDatastoreWhereDecorator;
use Nomad\Datastore\Traits\WithDatastoreCountDecorator;

class PostDatastore implements PostDatastoreInterface
{
    use WithDatastoreDecorator;
    use WithDatastorePrimaryKeyDecorator;
    use WithDatastoreWhereDecorator;
    use WithDatastoreCountDecorator;

    protected Datastore $datastoreHandler;

    public function __construct(PostDatastoreHandler $datastoreHandler)
    {
        $this->datastoreHandler = $datastoreHandler;
    }

    public function getPublishedPosts(): array
    {
        return $this->datastoreHandler->andWhere([
            ['column' => 'publishedDate', 'operator' => '<=', 'value' => date('Y-m-d H:i:s')]
        ]);
    }

    public function getByAuthor(int $authorId): array
    {
        return $this->datastoreHandler->andWhere([
            ['column' => 'authorId', 'operator' => '=', 'value' => $authorId]
        ]);
    }
}
```

**Why decorator traits?**

The decorator traits (`WithDatastoreDecorator`, `WithDatastorePrimaryKeyDecorator`, etc.) automatically delegate
standard operations like `create()`, `find()`, `update()`, and `delete()` to the `$datastoreHandler`. This eliminates
boilerplate code and lets you focus only on custom business methods like `getPublishedPosts()`.

Without these traits, you would need to manually write delegation methods:

```php
public function find(int $id): Post
{
    return $this->datastoreHandler->find($id);
}

public function create(array $attributes): Post
{
    return $this->datastoreHandler->create($attributes);
}
// ... and many more
```

The traits handle all of this automatically, keeping your datastore implementation clean and focused on business logic.

For detailed information about decorator patterns, see [Core Implementation](../packages/datastore/core-implementation).

---

## Step 5: Define the database table schema

Now that your datastore interfaces and Core implementation are complete, you need to define how data will be stored. Since we're building a database-backed datastore, we need to create a table schema that defines the database structure for storing posts.

Table classes define the database schema for your entity, including columns, indices, and versioning.

Create `Blog/Service/Datastores/Post/PostsTable.php`:

```php
<?php

namespace Blog\Service\Datastores\Post;

use Nomad\Database\Abstracts\Table;
use Nomad\Database\Factories\Column;
use Nomad\Database\Factories\Columns\PrimaryKeyFactory;
use Nomad\Database\Factories\Columns\DateCreatedFactory;
use Nomad\Database\Factories\Index;

class PostsTable extends Table
{
    public function getUnprefixedName(): string
    {
        return 'posts';
    }

    public function getAlias(): string
    {
        return 'pst';
    }

    public function getTableVersion(): string
    {
        return '1';
    }

    public function getSingularUnprefixedName(): string
    {
        return 'post';
    }

    public function getColumns(): array
    {
        return [
            (new PrimaryKeyFactory())->toColumn(),
            new Column('title', 'VARCHAR', [255], 'NOT NULL'),
            new Column('content', 'TEXT', null, 'NOT NULL'),
            new Column('authorId', 'BIGINT', null, 'NOT NULL'),
            new Column('publishedDate', 'DATETIME', null, 'NOT NULL'),
            (new DateCreatedFactory())->toColumn(),
        ];
    }

    public function getIndices(): array
    {
        return [
            new Index(['authorId'], 'idx_posts_author'),
            new Index(['publishedDate'], 'idx_posts_published'),
        ];
    }
}
```

**Key points**:

- `PrimaryKeyFactory` creates standard auto-incrementing primary key
- `DateCreatedFactory` creates timestamp column with automatic default
- `getTableVersion()` enables schema migrations
- Indices improve query performance for common lookups
- Column factories provide consistent definitions across your application

**About Table dependencies**:

The `Table` base class constructor requires several dependencies for database configuration:

```php
public function __construct(
    HasLocalDatabasePrefix  $localPrefixProvider,
    HasGlobalDatabasePrefix $globalPrefixProvider,
    HasCharsetProvider      $charsetProvider,
    HasCollateProvider      $collateProvider,
    TableSchemaService      $tableSchemaService,
    LoggerStrategy          $loggerStrategy
) {}
```

These dependencies are automatically injected by PHPNomad's dependency injection container when you register the table.
You don't need to manually provide them—the framework handles this through auto-wiring.

For complete table schema reference, see [Table Schema Definition](../packages/database/table-schema-definition).

---

## Step 6: Implement the database handler

With your table schema defined, you now need to implement the handler that connects your datastore to the database. The database handler uses the table definition to perform actual database operations like querying, inserting, updating, and deleting records.

The database handler extends PHPNomad's base handler and uses traits that provide the implementation of all standard datastore operations.

Create `Blog/Service/Datastores/Post/PostDatabaseDatastoreHandler.php`:

```php
<?php

namespace Blog\Service\Datastores\Post;

use Blog\Core\Datastores\Post\Interfaces\PostDatastoreHandler;
use Blog\Core\Models\Post;
use Blog\Core\Models\Adapters\PostAdapter;
use Nomad\Database\Abstracts\IdentifiableDatabaseDatastoreHandler;
use Nomad\Database\Providers\DatabaseServiceProvider;
use Nomad\Database\Services\TableSchemaService;
use Nomad\Database\Traits\WithDatastoreHandlerMethods;

class PostDatabaseDatastoreHandler extends IdentifiableDatabaseDatastoreHandler implements PostDatastoreHandler
{
    use WithDatastoreHandlerMethods;

    public function __construct(
        DatabaseServiceProvider $serviceProvider,
        PostsTable $table,
        PostAdapter $adapter,
        TableSchemaService $tableSchemaService
    ) {
        $this->serviceProvider = $serviceProvider;
        $this->table = $table;
        $this->modelAdapter = $adapter;
        $this->tableSchemaService = $tableSchemaService;
        $this->model = Post::class;
    }
}
```

**Why extend IdentifiableDatabaseDatastoreHandler?**

`IdentifiableDatabaseDatastoreHandler` is a base class that provides single-ID convenience methods by delegating to
compound-ID operations. For example, it implements:

```php
public function find(int $id): DataModel
{
    return $this->findCompound(['id' => $id]);
}
```

This saves you from writing boilerplate delegation code for every entity with a single integer primary key.

**Why use WithDatastoreHandlerMethods?**

The `WithDatastoreHandlerMethods` trait provides the actual implementation of all standard datastore operations:

- `create()` - Inserts records and broadcasts events
- `find()` / `where()` - Queries with caching
- `update()` / `delete()` - Modifications with event broadcasting
- Query building and condition handling
- Cache management for retrieved models

Without this trait, you would need to implement dozens of methods manually. The trait encapsulates all the database
interaction logic, caching strategies, and event broadcasting, allowing you to focus on entity-specific concerns.

**Constructor dependencies explained**:

- `DatabaseServiceProvider` - Provides query builder, cache service, event broadcasting
- `PostsTable` - Your table schema definition
- `PostAdapter` - Converts between Post models and arrays
- `TableSchemaService` - Manages table creation and migrations

For detailed information about database handlers, see [Database Handlers](../packages/database/database-handlers).

---

## Step 7: Create table installer

Your table schema is defined, but it won't create itself. You need an **installer** that creates the table when your application is activated or installed.

Create `Blog/Service/Installer.php`:

```php
<?php

namespace Blog\Service;

use PHPNomad\Di\Interfaces\CanSetContainer;
use PHPNomad\Di\Traits\HasSettableContainer;
use PHPNomad\Framework\Traits\CanInstallTables;
use PHPNomad\Loader\Interfaces\Loadable;
use Blog\Service\Datastores\Post\PostsTable;

class Installer implements CanSetContainer, Loadable
{
    use HasSettableContainer;
    use CanInstallTables;

    public function load(): void
    {
        $this->createTables();
    }

    protected function getTablesToInstall(): array
    {
        return [
            PostsTable::class,
        ];
    }
}
```

**How installers work:**

- `CanInstallTables` trait provides the table installation logic
- `getTablesToInstall()` returns an array of table classes to create
- `createTables()` checks if tables exist and creates/updates them
- Installers are idempotent—safe to run multiple times

**When installers run:**

Installers run during specific installation events, not on every page load:

- **WordPress plugins**: On plugin activation via `register_activation_hook()`
- **CLI applications**: During install commands
- **Manual triggers**: When deploying schema changes

The installer checks the current database state and only creates/updates tables when needed. If your table already exists and matches the schema version, the installer does nothing.

**Why this matters:**

Without an installer, your table definitions exist in code but never get executed. The installer is the bridge between your schema definition and the actual database structure.

---

## Step 8: Register with dependency injection

Register your datastore components with PHPNomad's dependency injection container so they can be auto-wired.

Create `Blog/Service/Initializer.php`:

```php
<?php

namespace Blog\Service;

use Blog\Core\Datastores\Post\Interfaces\PostDatastore as PostDatastoreInterface;
use Blog\Core\Datastores\Post\Interfaces\PostDatastoreHandler;
use Blog\Core\Datastores\Post\PostDatastore;
use Blog\Service\Datastores\Post\PostDatabaseDatastoreHandler;
use Nomad\Core\Interfaces\CanSetContainer;
use Nomad\Core\Interfaces\HasClassDefinitions;
use Nomad\Core\Traits\HasSettableContainer;

final class Initializer implements CanSetContainer, HasClassDefinitions
{
    use HasSettableContainer;

    public function getClassDefinitions(): array
    {
        return [
            // Bind concrete implementations to interfaces
            PostDatabaseDatastoreHandler::class => PostDatastoreHandler::class,
            PostDatastore::class => PostDatastoreInterface::class,
        ];
    }
}
```

**Key points**:
- `HasClassDefinitions` tells PHPNomad this initializer registers class bindings
- Maps concrete implementations to their interfaces
- The container auto-wires all constructor dependencies
- Format is: `Implementation::class => Interface::class`

### Create a Loader

Now create a loader that combines your initializers:

Create `Blog/Loader.php`:

```php
<?php

namespace Blog;

use PHPNomad\Di\Interfaces\CanSetContainer;
use PHPNomad\Di\Traits\HasSettableContainer;
use PHPNomad\Loader\Interfaces\Loadable;
use PHPNomad\Loader\Traits\CanLoadInitializers;
use Blog\Service\Initializer as ServiceInitializer;

class Loader implements CanSetContainer, Loadable
{
    use CanLoadInitializers;
    use HasSettableContainer;

    public function __construct()
    {
        $this->initializers = [
            new ServiceInitializer(),
        ];
    }

    public function load(): void
    {
        $this->loadInitializers();
    }
}
```

The loader collects all your initializers and loads them in order during bootstrap.

For complete initialization patterns, see [Creating and Managing Initializers](/core-concepts/bootstrapping/creating-and-managing-initializers).

---

## Step 9: Bootstrap your application

Finally, create an Application class that ties everything together:

Create `Blog/Application.php`:

```php
<?php

namespace Blog;

use PHPNomad\Di\Container;
use PHPNomad\Loader\Bootstrapper;
use Blog\Loader;
use Blog\Service\Installer;

class Application
{
    protected Container $container;

    public function __construct()
    {
        $this->container = new Container();
    }

    /**
     * Normal application initialization
     */
    public function init(): void
    {
        (new Bootstrapper(
            $this->container,
            new Loader()  // Loads all initializers
        ))->load();
    }

    /**
     * Run during plugin activation or installation
     */
    public function install(): void
    {
        (new Bootstrapper(
            $this->container,
            new Installer()  // Creates database tables
        ))->load();
    }
}
```

**How to use:**

For WordPress plugins, hook into activation and init:

```php
<?php
// blog-plugin.php

use Blog\Application;

// Install tables on plugin activation
register_activation_hook(__FILE__, function() {
    $app = new Application();
    $app->install();
});

// Normal app initialization
add_action('plugins_loaded', function() {
    $app = new Application();
    $app->init();
});
```

**Key insights:**

- `install()` runs the installer (creates tables) - only on activation
- `init()` loads initializers (registers classes) - runs on every page load
- The bootstrapper handles dependency resolution and initialization order

For complete bootstrapping documentation, see [Bootstrapping Introduction](/core-concepts/bootstrapping/introduction).

---

## Step 10: Use your datastore

Once registered, you can inject and use your datastore anywhere in your application:

```php
<?php

use Blog\Core\Datastores\Post\Interfaces\PostDatastore;

class BlogController
{
    public function __construct(
        private PostDatastore $postDatastore
    ) {}

    public function createPost(): void
    {
        $post = $this->postDatastore->create([
            'title' => 'My First Post',
            'content' => 'This is the content of my first blog post.',
            'authorId' => 1,
            'publishedDate' => date('Y-m-d H:i:s'),
        ]);

        echo "Created post with ID: " . $post->getId();
    }

    public function listPublishedPosts(): void
    {
        $posts = $this->postDatastore->getPublishedPosts();

        foreach ($posts as $post) {
            echo $post->title . "\n";
        }
    }

    public function findPost(int $id): void
    {
        $post = $this->postDatastore->find($id);
        echo $post->title;
    }

    public function updatePost(int $id): void
    {
        $this->postDatastore->update($id, [
            'title' => 'Updated Title'
        ]);
    }

    public function deletePost(int $id): void
    {
        $this->postDatastore->delete($id);
    }
}
```

The container automatically injects the `PostDatastore` implementation, which uses the database handler under the hood.

---

## What you have accomplished

You have built a complete database-backed datastore with all components:

- ✅ **Model** - Immutable value object representing a Post
- ✅ **Adapter** - Converts between Post models and database arrays
- ✅ **Core Interfaces** - Define what operations are possible
- ✅ **Core Implementation** - Business logic with decorator pattern
- ✅ **Table Schema** - Database structure with columns and indices
- ✅ **Database Handler** - Actual database operations
- ✅ **Installer** - Creates and manages database tables
- ✅ **Initializer** - Registers components with DI
- ✅ **Loader** - Combines initializers for bootstrapping
- ✅ **Application** - Orchestrates init and install workflows

Your datastore now supports:

- Creating, reading, updating, and deleting posts
- Custom business queries (published posts, posts by author)
- Automatic caching and event broadcasting
- Swappable implementations (could replace database with REST API)
- Proper table installation and schema versioning
- Clean bootstrapping and initialization patterns

---

## Next steps

Now that you have built your first datastore, explore these topics to deepen your understanding:

- **[Models and Identity](models-and-identity)** — Learn about compound keys and different identity patterns
- **[Core Datastore Layer](../packages/datastore/core-implementation)** — Master decorator patterns and custom business methods
- **[Database Handlers](../packages/database/database-handlers)** — Understand caching, events, and query building
- **[Table Schema Definition](../packages/database/table-schema-definition)** — Learn all column types, indices, and foreign keys
- **[Query Building](../packages/database/query-building)** — Build complex queries with conditions
- **[Junction Tables](../packages/database/junction-tables)** — Implement many-to-many relationships
- **[Advanced Patterns](../advanced/advanced-patterns)** — Soft deletes, audit trails, and optimization
- **[Logger Package](../packages/logger/introduction)** — LoggerStrategy interface for handler logging

---

## Summary

Building a PHPNomad datastore involves creating models, adapters, Core interfaces and implementations, database schemas,
database handlers, and dependency injection registration. The pattern separates business logic (Core) from
implementation details (Service), enabling flexible, testable, and maintainable code. Decorator traits eliminate
boilerplate, while base classes and handler traits provide robust database operations with caching and event support.
