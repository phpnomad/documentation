# Datastore Interface

The `Datastore` interface is the **foundational contract** for all data access in PHPNomad. It defines three core operations—`get()`, `save()`, and `delete()`—that form the basis of every datastore implementation. Every other datastore interface extends from this one.

## Interface Definition

```php
interface Datastore
{
    /**
     * Retrieves a collection of models based on the provided criteria.
     *
     * @param array $args Filtering criteria (implementation-defined)
     * @return iterable<Model> Collection of models matching the criteria
     */
    public function get(array $args = []): iterable;

    /**
     * Persists a model to storage (create or update).
     *
     * @param Model $item The model to save
     * @return Model The saved model (may include generated IDs or timestamps)
     */
    public function save(Model $item): Model;

    /**
     * Removes a model from storage.
     *
     * @param Model $item The model to delete
     * @return void
     */
    public function delete(Model $item): void;
}
```

## Methods

### `get(array $args = []): iterable`

Retrieves a collection of models matching the provided criteria.

**Parameters:**
* `$args` — Filtering criteria as an associative array. The structure is **implementation-defined**, meaning each datastore decides what keys are valid.

**Returns:**
* An iterable collection of `Model` objects (typically an array or generator).

**Common `$args` patterns:**

```php
// Filter by a single field
$posts = $datastore->get(['author_id' => 123]);

// Multiple conditions (AND logic, typically)
$posts = $datastore->get([
    'author_id' => 123,
    'status' => 'published'
]);

// Limit results
$posts = $datastore->get(['limit' => 10]);

// Pagination
$posts = $datastore->get(['limit' => 10, 'offset' => 20]);

// Empty args = all records
$posts = $datastore->get();
```

**When to use:**
* Fetching multiple records with simple filtering
* When you don't need advanced query building (use `where()` for that)
* Quick lookups by known fields

**Example:**

```php
// Get all published posts by a specific author
$posts = $postDatastore->get([
    'author_id' => 123,
    'status' => 'published'
]);

foreach ($posts as $post) {
    echo $post->title . "\n";
}
```

---

### `save(Model $item): Model`

Persists a model to storage. This method handles both **create** and **update** operations—implementations typically determine which based on whether the model has a primary key.

**Parameters:**
* `$item` — The model to persist.

**Returns:**
* The saved model, potentially with updated fields (e.g., auto-generated IDs, timestamps).

**Behavior:**
* **Create:** If the model lacks a primary key (or it's `null`), a new record is created.
* **Update:** If the model has a primary key, the existing record is updated.

**Example: Creating a new record**

```php
$newPost = new Post(
    id: null, // No ID yet
    title: 'My First Post',
    content: 'Hello world!',
    authorId: 123,
    publishedDate: new DateTime()
);

$savedPost = $postDatastore->save($newPost);

echo $savedPost->id; // Now has an auto-generated ID
```

**Example: Updating an existing record**

```php
$existingPost = $postDatastore->find(42);

// Models are immutable, so we create a new instance with updated fields
$updatedPost = new Post(
    id: $existingPost->id,
    title: 'Updated Title',
    content: $existingPost->content,
    authorId: $existingPost->authorId,
    publishedDate: $existingPost->publishedDate
);

$postDatastore->save($updatedPost);
```

**When to use:**
* Creating new records
* Updating existing records
* Persisting changes after business logic

---

### `delete(Model $item): void`

Removes a model from storage.

**Parameters:**
* `$item` — The model to delete. Implementations typically extract the primary key from the model to perform the deletion.

**Returns:**
* `void` — no return value.

**Behavior:**
* The model is removed from storage.
* If the model doesn't exist, behavior is implementation-defined (may throw an exception or silently succeed).

**Example:**

```php
$post = $postDatastore->find(42);

$postDatastore->delete($post);

// Post 42 is now removed from storage
```

**When to use:**
* Removing records after business logic determines they should be deleted
* Cleanup operations
* Cascading deletes (if not handled by database constraints)

---

## Usage Patterns

### Basic CRUD Operations

The `Datastore` interface provides everything needed for simple create-read-update-delete operations:

```php
// CREATE
$newPost = new Post(null, 'Title', 'Content', 123, new DateTime());
$savedPost = $datastore->save($newPost);

// READ
$posts = $datastore->get(['author_id' => 123]);

// UPDATE
$updatedPost = new Post(
    $savedPost->id,
    'New Title',
    $savedPost->content,
    $savedPost->authorId,
    $savedPost->publishedDate
);
$datastore->save($updatedPost);

// DELETE
$datastore->delete($updatedPost);
```

### Working with Immutable Models

PHPNomad models are **immutable**—once created, their properties cannot change. To "update" a model, you create a new instance with the changed values:

```php
$post = $datastore->find(42);

// Create new instance with updated title
$updatedPost = new Post(
    id: $post->id,
    title: 'New Title',  // Changed
    content: $post->content,
    authorId: $post->authorId,
    publishedDate: $post->publishedDate
);

$datastore->save($updatedPost);
```

This ensures data consistency and makes debugging easier (you always know where state changes).

### Filtering Semantics

The `$args` parameter in `get()` is **implementation-defined**, but most implementations follow these conventions:

* **Keys are column names** — `['author_id' => 123]` filters by `author_id`.
* **Multiple keys use AND logic** — `['author_id' => 123, 'status' => 'published']` means "author is 123 AND status is published".
* **Special keys control behavior** — `limit`, `offset`, `order_by` are common.

For complex queries (OR conditions, comparisons like `>` or `LIKE`), use [`DatastoreHasWhere`](/packages/datastore/interfaces/datastore-has-where) instead.

## Extending the Base Interface

Most datastores extend `Datastore` with additional capabilities:

```php
interface PostDatastore extends Datastore, DatastoreHasPrimaryKey
{
    // Inherits get(), save(), delete()
    // Adds find() from DatastoreHasPrimaryKey
    
    // Custom business methods can be added
    public function findPublishedPosts(int $authorId): iterable;
}
```

See [Datastore Interfaces Overview](/packages/datastore/interfaces/introduction) for extension patterns.

## Implementation Notes

When implementing this interface:

* **`get()` should return an empty iterable** if no matches are found (not `null`, not an exception).
* **`save()` should be idempotent** — calling it multiple times with the same model should produce the same result.
* **`delete()` should not throw** if the model doesn't exist (graceful degradation).
* **Models should be validated** before persistence (use validation services, not handler code).

## What's Next

* [DatastoreHasPrimaryKey](/packages/datastore/interfaces/datastore-has-primary-key) — adds `find(int $id)` for ID-based lookups
* [DatastoreHasWhere](/packages/datastore/interfaces/datastore-has-where) — adds query-builder-style filtering
* [DatastoreHasCounts](/packages/datastore/interfaces/datastore-has-counts) — adds `count()` for efficient counting
* [Core Implementation](/packages/datastore/core-implementation) — how to implement these interfaces
