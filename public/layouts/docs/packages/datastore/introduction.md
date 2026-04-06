# Datastore

`phpnomad/datastore` is a **storage-agnostic data access layer** that separates **what data operations you need** from **how they're implemented**. It's designed to let you describe **models, interfaces, and operations** in a way that's **independent of the persistence backend** you plug into.

At its core:

* **Models** represent domain entities as immutable value objects with no persistence awareness.
* **Datastores** define business-level data operations through interfaces.
* **DatastoreHandlers** provide the contract for concrete implementations.
* **ModelAdapters** convert between models and storage representations.
* **Decorator traits** eliminate boilerplate delegation code.

By separating data access **definition** (what operations exist, what they require, what they return) from **implementation** (database queries, API calls, cache lookups), you get datastores that can move between storage backends without rewriting business logic.

---

## Key ideas at a glance

* **Datastore** — Your public API defining business-level data operations for an entity.
* **DatastoreHandler** — The contract that concrete storage implementations fulfill.
* **Model** — An immutable value object representing a domain entity, independent of persistence.
* **ModelAdapter** — Converts between models and storage representations (arrays, JSON, etc.).
* **Decorator traits** — Automatically delegate standard operations to handlers, keeping code lean.

---

## The data access lifecycle

When your application performs a data operation through a datastore, it moves through a consistent sequence:

```
Application → Datastore → Handler → Storage (Database/API/Cache) → Adapter → Model
```

### Application layer

Your controllers, services, or other application code depend on the `Datastore` interface. They call methods like `find()`, `create()`, `where()`, or custom business methods like `getPublishedPosts()`.

```php
$post = $postDatastore->find(123);
$published = $postDatastore->getPublishedPosts();
```

The application never knows whether posts come from a database, REST API, or cache. It only knows the operations available on the `PostDatastore` interface.

### Datastore layer

The **Datastore** is your public API. It extends base interfaces (optionally) and adds custom business methods. Standard operations are delegated to the handler using decorator traits. Custom methods compose handler primitives to implement business logic.

```php
class PostDatastore implements PostDatastoreInterface
{
    use WithDatastoreDecorator;
    use WithDatastorePrimaryKeyDecorator;

    protected Datastore $datastoreHandler;

    public function __construct(PostDatastoreHandler $datastoreHandler)
    {
        $this->datastoreHandler = $datastoreHandler;
    }

    // Custom business method
    public function getPublishedPosts(): array
    {
        return $this->datastoreHandler->where([
            ['column' => 'status', 'operator' => '=', 'value' => 'published']
        ]);
    }
}
```

### Handler layer

The **DatastoreHandler** is the contract for storage implementations. It extends the same base interfaces as the Datastore but typically contains no custom business methods. Handlers focus on the primitives: create, find, update, delete, query.

Different implementations exist for different storage backends:
- `PostDatabaseDatastoreHandler` — queries a database
- `PostGraphQLDatastoreHandler` — calls a GraphQL API
- `PostRESTDatastoreHandler` — makes HTTP requests
- `PostCacheDatastoreHandler` — reads from cache

### Storage layer

The handler interacts with the actual storage mechanism. For databases, this means SQL queries. For APIs, this means HTTP requests. For caches, this means key-value lookups.

The storage layer knows nothing about models or business logic. It works with raw data representations (arrays, JSON objects, database rows).

### Adapter layer

The **ModelAdapter** converts between storage representations and domain models. When reading, it takes raw data (arrays) and constructs model objects. When writing, it takes model objects and produces storable data.

```php
class PostAdapter implements ModelAdapter
{
    public function toModel(array $data): Post
    {
        return new Post(
            id: $data['id'],
            title: $data['title'],
            content: $data['content']
        );
    }

    public function toArray(Post $model): array
    {
        return [
            'id' => $model->getId(),
            'title' => $model->title,
            'content' => $model->content
        ];
    }
}
```

### Model layer

The **Model** is the final result — a domain entity your application can use. Models are immutable value objects with public readonly properties. They contain no persistence logic and don't know where they came from.

```php
class Post implements DataModel, HasSingleIntIdentity
{
    use WithSingleIntIdentity;

    public function __construct(
        int $id,
        public readonly string $title,
        public readonly string $content
    ) {
        $this->id = $id;
    }
}
```

---

## Why separation matters

### Storage independence

By depending only on the `Datastore` interface, your application code remains portable. If posts initially come from a database but later need to come from a CMS API, you swap the handler implementation. Application code doesn't change.

```php
// Day 1: Database implementation
$container->bind(PostDatastoreHandler::class, PostDatabaseDatastoreHandler::class);

// Day 90: Switch to REST API
$container->bind(PostDatastoreHandler::class, PostRESTDatastoreHandler::class);

// Application code unchanged
$post = $postDatastore->find(123);
```

### Testability

Datastores are easy to test. Mock the handler, inject it into the datastore, and verify business methods work correctly without touching a real database or API.

### Clear contracts

The separation between `Datastore` (what consumers need) and `DatastoreHandler` (what implementations provide) makes contracts explicit. Consumers depend on business operations. Implementations provide storage primitives.

---

## Core interfaces

The datastore package provides several base interfaces you can extend:

### Datastore

The foundational interface. All datastores extend `Datastore`, which defines basic create and update operations.

```php
interface Datastore
{
    public function create(array $attributes): DataModel;
    public function updateCompound(array $ids, array $attributes): void;
}
```

### DatastoreHasPrimaryKey

Adds operations for entities with single integer IDs: find, update, delete by ID.

```php
interface DatastoreHasPrimaryKey
{
    public function find(int $id): DataModel;
    public function findMultiple(array $ids): array;
    public function update(int $id, array $attributes): void;
    public function delete(int $id): void;
}
```

### DatastoreHasWhere

Adds query operations with conditions: where, andWhere, orWhere, deleteWhere, findBy.

```php
interface DatastoreHasWhere
{
    public function where(array $conditions, ?int $limit = null, ...): array;
    public function andWhere(array $conditions, ?int $limit = null, ...): array;
    public function orWhere(array $conditions, ?int $limit = null, ...): array;
    public function deleteWhere(array $conditions): void;
    public function findBy(string $field, $value): DataModel;
}
```

### DatastoreHasCounts

Adds counting operations for query results.

You're not required to extend these interfaces. For APIs with limited operations, define only what you need.

See [Datastore Interfaces](interfaces/introduction) for complete documentation.

---

## Decorator traits

When your `Datastore` and `DatastoreHandler` extend the same base interfaces, decorator traits eliminate boilerplate delegation code.

```php
class PostDatastore implements PostDatastoreInterface
{
    use WithDatastoreDecorator;              // Delegates: create, updateCompound
    use WithDatastorePrimaryKeyDecorator;     // Delegates: find, update, delete
    use WithDatastoreWhereDecorator;          // Delegates: where, andWhere, orWhere

    protected Datastore $datastoreHandler;

    // Only implement custom business methods
    public function getPublishedPosts(): array
    {
        return $this->datastoreHandler->where([...]);
    }
}
```

Without traits, you'd manually write dozens of delegation methods. Traits handle standard operations automatically.

See [Decorator Traits](traits/introduction) for complete documentation.

---

## When to use this package

Use `phpnomad/datastore` when:

- You need storage-agnostic data access
- Portability between storage backends is important
- You want strong separation between domain and infrastructure
- Multiple implementations of the same entity are anticipated (database today, API tomorrow)
- Testing domain logic independently of storage is critical

For simple applications with a single, stable storage mechanism and no portability requirements, this abstraction may be overkill. The datastore pattern shines when flexibility and future adaptability matter.

---

## Working with databases

While the datastore package is storage-agnostic, most applications use databases. The `phpnomad/database` package provides concrete database implementations of the datastore interfaces, including table schema definitions, query builders, caching, and event broadcasting.

See [Database Package](../database/introduction) for database-specific documentation.

---

## Working with other backends

The datastore package isn't limited to databases. You can implement handlers for:

- **REST APIs** — Make HTTP requests, convert JSON to models
- **GraphQL APIs** — Execute GraphQL queries, map responses to models
- **Cache layers** — Read from Redis/Memcached with fallback to primary storage
- **In-memory stores** — Arrays or collections for testing
- **File systems** — JSON/XML files as simple persistence

See [Integration Guide](integration-guide) for implementing custom handlers.

---

## Package components

### Required reading

- **[Core Implementation](core-implementation)** — Directory structure, naming conventions, Datastore vs DatastoreHandler distinction, decorator pattern usage
- **[Datastore Interfaces](interfaces/introduction)** — Complete interface reference
- **[Model Adapters](model-adapters)** — How to create adapters

### Reference

- **[Decorator Traits](traits/introduction)** — All available traits and their delegated methods
- **[Integration Guide](integration-guide)** — Implementing custom storage backends

---

## Relationship to other packages

- **[phpnomad/database](../database/introduction)** — Concrete database implementations of datastore interfaces
- **phpnomad/models** — Provides `DataModel` interface and identity traits (covered in [Models and Identity](../../core-concepts/models-and-identity))

---

## Next steps

- **New to datastores?** Start with [Getting Started Tutorial](../../core-concepts/getting-started-tutorial)
- **Understanding the architecture?** Read [Overview and Architecture](../../core-concepts/overview-and-architecture)
- **Ready to implement?** See [Core Implementation](core-implementation)
- **Need database persistence?** Check [Database Package](../database/introduction)
