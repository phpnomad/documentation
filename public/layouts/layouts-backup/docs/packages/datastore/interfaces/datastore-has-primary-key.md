# DatastoreHasPrimaryKey Interface

The `DatastoreHasPrimaryKey` interface extends [`Datastore`](/packages/datastore/interfaces/datastore) to add **primary key-based operations**. It provides the `find()` method for fast single-record lookups by ID—one of the most common operations in data access.

This interface assumes your storage uses a **single integer primary key** (typically named `id`). If your model uses compound keys or non-integer identifiers, this interface may not apply.

## Interface Definition

```php
interface DatastoreHasPrimaryKey extends Datastore
{
    /**
     * Finds a single record by its primary key.
     *
     * @param int $id The primary key value
     * @return Model The matching model
     * @throws RecordNotFoundException if no record exists with the given ID
     */
    public function find(int $id): Model;
}
```

## Method

### `find(int $id): Model`

Retrieves a single model by its primary key value.

**Parameters:**
* `$id` — The primary key (typically an auto-increment integer).

**Returns:**
* A single `Model` instance.

**Throws:**
* `RecordNotFoundException` — if no record exists with the given ID.

**When to use:**
* Fetching a known record by ID
* Loading related entities (e.g., "get the author for this post")
* REST endpoints like `GET /posts/42`

**Example: basic lookup**

```php
try {
    $post = $postDatastore->find(42);
    echo $post->title;
} catch (RecordNotFoundException $e) {
    echo "Post not found";
}
```

**Example: loading related entity**

```php
$post = $postDatastore->find(123);

// Load the author using the foreign key
$author = $authorDatastore->find($post->authorId);

echo "Post '{$post->title}' by {$author->name}";
```

---

## Why This Interface Exists

Primary key lookups are:
* **Fast** — indexed lookups are O(log n) or O(1) in most databases.
* **Common** — most business logic operates on single entities.
* **Predictable** — you know you'll get exactly one result (or an exception).

By separating `find()` into its own interface, PHPNomad allows datastores to opt in or out based on their storage model. For example:
* **Database-backed datastores** → implement this (they have primary keys).
* **Log aggregators or event streams** → don't implement this (they don't have primary keys).

## Usage Patterns

### Service Layer Integration

Services typically depend on `DatastoreHasPrimaryKey` when they need ID-based lookups:

```php
final class PublishPostService
{
    public function __construct(
        private PostDatastore $posts // Assumes DatastoreHasPrimaryKey
    ) {}

    public function publish(int $postId): void
    {
        $post = $this->posts->find($postId);

        // Business logic: create new model with updated date
        $publishedPost = new Post(
            id: $post->id,
            title: $post->title,
            content: $post->content,
            authorId: $post->authorId,
            publishedDate: new DateTime() // Set publish date
        );

        $this->posts->save($publishedPost);
    }
}
```

### REST Controller Example

REST endpoints often map directly to `find()`:

```php
final class GetPostController implements Controller
{
    public function __construct(
        private PostDatastore $posts,
        private Response $response
    ) {}

    public function getEndpoint(): string
    {
        return '/posts/{id}';
    }

    public function getMethod(): string
    {
        return Method::Get;
    }

    public function getResponse(Request $request): Response
    {
        $id = (int) $request->getParam('id');

        try {
            $post = $this->posts->find($id);
            
            return $this->response
                ->setStatus(200)
                ->setJson(['post' => $post]);
        } catch (RecordNotFoundException $e) {
            return $this->response
                ->setStatus(404)
                ->setJson(['error' => 'Post not found']);
        }
    }
}
```

### Error Handling

Always handle `RecordNotFoundException` when calling `find()`:

```php
// ✅ GOOD: explicit error handling
try {
    $post = $postDatastore->find($id);
    // ... use post
} catch (RecordNotFoundException $e) {
    // Handle gracefully
}

// ❌ BAD: unhandled exception crashes the application
$post = $postDatastore->find($id); // May throw!
```

## Relationship to Other Interfaces

### vs. `get()`

Both `find()` and `get()` can fetch records, but they serve different purposes:

| Method | Returns | When Not Found | Use Case |
|--------|---------|----------------|----------|
| `find($id)` | Single model | Throws exception | Known ID, expect one result |
| `get(['id' => $id])` | Iterable (0 or 1 item) | Empty iterable | Query by criteria, may return none |

**Example comparison:**

```php
// Using find() - throws if not found
try {
    $post = $datastore->find(42);
} catch (RecordNotFoundException $e) {
    // Handle not found
}

// Using get() - returns empty if not found
$posts = $datastore->get(['id' => 42]);
if (empty($posts)) {
    // Handle not found
}
$post = $posts[0] ?? null;
```

Use `find()` when you **expect** the record to exist. Use `get()` when existence is uncertain.

### Combining with Other Extensions

Most datastores implement multiple interfaces:

```php
interface PostDatastore extends 
    Datastore,
    DatastoreHasPrimaryKey,
    DatastoreHasWhere,
    DatastoreHasCounts
{
    // get(), save(), delete() from Datastore
    // find() from DatastoreHasPrimaryKey
    // where() from DatastoreHasWhere
    // count() from DatastoreHasCounts
}
```

This gives consumers a full set of operations.

## Implementation with Decorator Traits

If your Core implementation just delegates to a handler, use [`WithDatastorePrimaryKeyDecorator`](/packages/datastore/traits/with-datastore-primary-key-decorator):

```php
final class PostDatastoreImpl implements PostDatastore
{
    use WithDatastorePrimaryKeyDecorator;

    public function __construct(
        private DatastoreHandlerHasPrimaryKey $handler
    ) {}
}
```

The trait provides `find()`, `get()`, `save()`, and `delete()` automatically.

## Implementation Notes

When implementing this interface:

* **`find()` must throw `RecordNotFoundException`** if the ID doesn't exist—do not return `null`.
* **Primary key should be indexed** in your storage layer for performance.
* **Thread safety** — `find()` should always return the latest data (no stale reads unless caching is explicit).

## When NOT to Implement This Interface

Skip `DatastoreHasPrimaryKey` if:
* Your storage doesn't have primary keys (e.g., logs, events).
* You use compound keys (use custom methods instead).
* You use non-integer IDs (e.g., UUIDs—extend the interface with `findByUuid()` or similar).

## What's Next

* [Datastore Interface](/packages/datastore/interfaces/datastore) — the base interface this extends
* [DatastoreHasWhere](/packages/datastore/interfaces/datastore-has-where) — query-builder filtering
* [WithDatastorePrimaryKeyDecorator](/packages/datastore/traits/with-datastore-primary-key-decorator) — auto-implement this interface
