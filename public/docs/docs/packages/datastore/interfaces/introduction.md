# Datastore Interfaces

Datastore interfaces define the **public API** for data access in PHPNomad. They describe what operations consumers can perform without tying them to any specific storage implementation. This separation is what makes datastores portable: the same interface works whether your data lives in a database, a REST API, in-memory cache, or a flat file.

At the core, every datastore interface extends from **`Datastore`**, which provides basic operations like `get()`, `save()`, and `delete()`. From there, you can layer on additional capabilities through **extension interfaces** that add primary key lookups, querying, counting, and more.

## Why Interfaces Matter

In PHPNomad, **interfaces are contracts** that your application code depends on. By coding against `Datastore` or `DatastoreHasPrimaryKey`, you're expressing *what you need* without caring *how it's implemented*.

This matters because:

* **Portability** — swap implementations without touching application code (e.g., move from database to REST).
* **Testability** — mock or stub the interface in tests without spinning up real storage.
* **Clarity** — each interface declares exactly what operations it supports, making API boundaries obvious.

When you write a service or controller that depends on a datastore, you inject the **interface**, not a concrete class. The DI container handles the rest.

## The Base Interface: `Datastore`

The `Datastore` interface is the **minimal contract** every datastore must implement. It provides three core operations:

```php
interface Datastore
{
    /**
     * Retrieves a collection of models based on the provided criteria.
     */
    public function get(array $args = []): iterable;

    /**
     * Persists a model to storage.
     */
    public function save(Model $item): Model;

    /**
     * Removes a model from storage.
     */
    public function delete(Model $item): void;
}
```

### What this enables

* **Fetch** — `get($args)` returns an iterable collection of models filtered by arbitrary criteria.
* **Persist** — `save($model)` writes a model to storage (create or update).
* **Remove** — `delete($model)` deletes a model from storage.

This is enough to build most CRUD operations. When you need more specific operations (like fetching by ID or running WHERE clauses), you extend this base with additional interfaces.

## Extension Interfaces

PHPNomad provides several **extension interfaces** that add specific capabilities to the base `Datastore` contract. Each one is focused on a single concern, and you compose them as needed.

### `DatastoreHasPrimaryKey`

Adds the ability to **fetch by primary key** — a common pattern for single-record lookups.

```php
interface DatastoreHasPrimaryKey extends Datastore
{
    /**
     * Finds a single record by its primary key.
     * 
     * @throws RecordNotFoundException if not found
     */
    public function find(int $id): Model;
}
```

**When to use:** Your datastore has a single integer primary key (e.g., `id`), and you need fast lookups by ID.

**Example:**
```php
$post = $postDatastore->find(42);
```

---

### `DatastoreHasWhere`

Adds **query-builder-style filtering** with a `where()` method that returns a scoped query interface.

```php
interface DatastoreHasWhere extends Datastore
{
    /**
     * Returns a query interface for building WHERE clauses.
     */
    public function where(): DatastoreWhereQuery;
}
```

**When to use:** You need to filter records by multiple criteria, and `get($args)` isn't expressive enough.

**Example:**
```php
$posts = $postDatastore
    ->where()
    ->equals('authorId', 123)
    ->greaterThan('publishedDate', '2024-01-01')
    ->getResults();
```

---

### `DatastoreHasCounts`

Adds **counting operations** without fetching all records.

```php
interface DatastoreHasCounts extends Datastore
{
    /**
     * Returns the total number of records matching the criteria.
     */
    public function count(array $args = []): int;
}
```

**When to use:** You need to know *how many* records exist without loading them all into memory (e.g., pagination totals).

**Example:**
```php
$totalPosts = $postDatastore->count(['authorId' => 123]);
```

---

## Composing Interfaces

In practice, most datastores implement **multiple interfaces** to provide a rich API. For example:

```php
interface PostDatastore extends 
    Datastore, 
    DatastoreHasPrimaryKey, 
    DatastoreHasWhere, 
    DatastoreHasCounts
{
    // Custom business methods can also be added here
    public function findPublishedPosts(int $authorId): iterable;
}
```

This gives consumers:
* Basic operations via `Datastore`
* ID lookups via `DatastoreHasPrimaryKey`
* Complex queries via `DatastoreHasWhere`
* Efficient counting via `DatastoreHasCounts`
* Domain-specific methods like `findPublishedPosts()`

## The Minimal API Approach

Not every datastore needs all these capabilities. If your storage layer doesn't support primary keys (e.g., a log aggregator or event stream), you might only implement `Datastore`.

**Example: minimal datastore**

```php
interface AuditLogDatastore extends Datastore
{
    // Only needs get(), save(), delete()
    // No primary keys, no WHERE queries
}
```

This is valid and often preferable. **Only add interfaces when you actually need the capability**, not because other datastores have them.

## Working with DatastoreHandlers

While datastore interfaces define the **public API**, `DatastoreHandler` interfaces define the **implementation contract** for storage backends.

For example:
* `Datastore` is what your application depends on.
* `DatastoreHandler` is what the database/REST/file adapter implements.

The [Core implementation](/packages/datastore/core-implementation) sits between these two, delegating calls from the public interface to the handler. This separation keeps business logic independent of storage details.

See [DatastoreHandler interfaces](/packages/database/handlers/introduction) for the storage-side contracts.

## Best Practices

When designing or using datastore interfaces:

* **Depend on interfaces, not implementations** — inject `PostDatastore`, not `DatabasePostDatastore`.
* **Only extend what you need** — don't add `DatastoreHasPrimaryKey` if you don't have primary keys.
* **Keep interfaces focused** — each extension adds one capability. Don't create bloated "god interfaces."
* **Use custom methods sparingly** — add domain-specific methods to your interface, but prefer composing standard operations when possible.

## What's Next

To understand how these interfaces are implemented, see:

* [Core Implementation](/packages/datastore/core-implementation) — how to build the layer that implements these interfaces
* [Decorator Traits](/packages/datastore/traits/introduction) — eliminate boilerplate when delegating to handlers
* [Database Handlers](/packages/database/handlers/introduction) — how storage backends implement the handler contract
