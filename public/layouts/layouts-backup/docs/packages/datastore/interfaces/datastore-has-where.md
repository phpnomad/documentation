# DatastoreHasWhere Interface

The `DatastoreHasWhere` interface extends [`Datastore`](/packages/datastore/interfaces/datastore) to add **query-builder-style filtering**. It provides the `where()` method, which returns a fluent query interface for building complex queries with multiple conditions, comparisons, and sorting.

This interface is for datastores that support **rich querying** beyond simple key-value filtering. If your storage supports SQL, this interface maps naturally to `WHERE` clauses.

## Interface Definition

```php
interface DatastoreHasWhere extends Datastore
{
    /**
     * Returns a query interface for building WHERE clauses.
     *
     * @return DatastoreWhereQuery A fluent query builder
     */
    public function where(): DatastoreWhereQuery;
}
```

## Method

### `where(): DatastoreWhereQuery`

Returns a query builder instance for constructing filtered queries.

**Returns:**
* A `DatastoreWhereQuery` object that provides methods like `equals()`, `greaterThan()`, `lessThan()`, `in()`, `like()`, `orderBy()`, `limit()`, and `getResults()`.

**When to use:**
* Complex filtering (multiple conditions, OR logic, comparisons)
* Sorting results
* Pagination with complex criteria
* Queries that don't map cleanly to `get(['key' => 'value'])`

**Example: basic filtering**

```php
$posts = $postDatastore
    ->where()
    ->equals('author_id', 123)
    ->getResults();
```

**Example: multiple conditions**

```php
$posts = $postDatastore
    ->where()
    ->equals('author_id', 123)
    ->greaterThan('published_date', '2024-01-01')
    ->lessThanOrEqual('published_date', '2024-12-31')
    ->getResults();
```

**Example: OR conditions**

```php
$posts = $postDatastore
    ->where()
    ->equals('status', 'published')
    ->or()
    ->equals('status', 'featured')
    ->getResults();
```

**Example: sorting and pagination**

```php
$posts = $postDatastore
    ->where()
    ->equals('author_id', 123)
    ->orderBy('published_date', 'DESC')
    ->limit(10)
    ->offset(20)
    ->getResults();
```

---

## DatastoreWhereQuery API

The `DatastoreWhereQuery` interface provides a fluent API for building queries. Implementations typically support:

### Comparison Methods

* `equals(string $field, mixed $value)` — `field = value`
* `notEquals(string $field, mixed $value)` — `field != value`
* `greaterThan(string $field, mixed $value)` — `field > value`
* `greaterThanOrEqual(string $field, mixed $value)` — `field >= value`
* `lessThan(string $field, mixed $value)` — `field < value`
* `lessThanOrEqual(string $field, mixed $value)` — `field <= value`
* `in(string $field, array $values)` — `field IN (values)`
* `notIn(string $field, array $values)` — `field NOT IN (values)`
* `like(string $field, string $pattern)` — `field LIKE pattern`
* `isNull(string $field)` — `field IS NULL`
* `isNotNull(string $field)` — `field IS NOT NULL`

### Logical Operators

* `and()` — AND condition (default between chained methods)
* `or()` — OR condition

### Ordering and Pagination

* `orderBy(string $field, string $direction = 'ASC')` — Sort results
* `limit(int $count)` — Limit number of results
* `offset(int $count)` — Skip N results (for pagination)

### Execution

* `getResults(): iterable` — Execute the query and return matching models
* `count(): int` — Count matching records (if combined with `DatastoreHasCounts`)
* `delete(): void` — Delete matching records (if supported)

---

## Usage Patterns

### Service Layer Queries

Services use `where()` for business queries:

```php
final class PostService
{
    public function __construct(
        private PostDatastore $posts
    ) {}

    public function getRecentPublishedPosts(int $authorId, int $limit = 10): iterable
    {
        return $this->posts
            ->where()
            ->equals('author_id', $authorId)
            ->lessThanOrEqual('published_date', new DateTime())
            ->orderBy('published_date', 'DESC')
            ->limit($limit)
            ->getResults();
    }

    public function getPostsByTag(string $tag): iterable
    {
        return $this->posts
            ->where()
            ->like('tags', "%{$tag}%")
            ->getResults();
    }
}
```

### Complex Filtering Example

```php
// Find posts that are:
// - By author 123 OR author 456
// - Published in 2024
// - Status is "published" or "featured"
// - Sorted by views (descending)

$posts = $postDatastore
    ->where()
    ->in('author_id', [123, 456])
    ->greaterThanOrEqual('published_date', '2024-01-01')
    ->lessThan('published_date', '2025-01-01')
    ->in('status', ['published', 'featured'])
    ->orderBy('view_count', 'DESC')
    ->limit(20)
    ->getResults();
```

### Counting with Queries

If your datastore also implements `DatastoreHasCounts`, you can count query results:

```php
$query = $postDatastore
    ->where()
    ->equals('author_id', 123)
    ->greaterThan('published_date', '2024-01-01');

$count = $query->count(); // How many match?
$posts = $query->getResults(); // Fetch them
```

### Deleting with Queries

Some implementations support `delete()` on queries:

```php
// Delete all draft posts older than 30 days
$postDatastore
    ->where()
    ->equals('status', 'draft')
    ->lessThan('created_at', new DateTime('-30 days'))
    ->delete();
```

**Note:** Not all datastores support query-based deletion. Check your implementation.

---

## Why This Interface Exists

The base [`get()`](/packages/datastore/interfaces/datastore) method works for simple queries:

```php
$posts = $datastore->get(['author_id' => 123]);
```

But it breaks down for:
* **Comparisons** — `get(['views >' => 1000])` is awkward and non-standard.
* **OR logic** — `get(['status' => 'published OR featured'])` doesn't work.
* **Sorting** — `get()` doesn't provide ordering control.

`DatastoreHasWhere` solves this with a fluent, expressive API that maps cleanly to SQL and other query languages.

## Relationship to Other Interfaces

### vs. `get()`

| Method | Use Case | Query Complexity |
|--------|----------|------------------|
| `get(['key' => 'value'])` | Simple key-value filtering | Low |
| `where()->equals()->getResults()` | Complex queries with comparisons, OR logic, sorting | High |

**Rule of thumb:** If you can express it as `['key' => 'value']`, use `get()`. Otherwise, use `where()`.

### Combining with Other Extensions

```php
interface PostDatastore extends 
    Datastore,
    DatastoreHasPrimaryKey,
    DatastoreHasWhere,
    DatastoreHasCounts
{
    // get(), save(), delete()
    // find()
    // where()
    // count()
}
```

This gives you all query capabilities.

---

## Implementation with Decorator Traits

Use [`WithDatastoreWhereDecorator`](/packages/datastore/traits/with-datastore-where-decorator) to auto-implement:

```php
final class PostDatastoreImpl implements DatastoreHasWhere
{
    use WithDatastoreWhereDecorator;

    public function __construct(
        private DatastoreHandlerHasWhere $handler
    ) {}
}
```

The trait delegates `where()` to the handler, which returns its query builder.

---

## Implementation Notes

When implementing this interface:

* **`where()` returns a new query instance** — don't modify shared state.
* **Queries are immutable** — each method call returns a new query object (or mutates and returns `$this` for chaining).
* **`getResults()` executes the query** — it's the only method that hits storage.
* **Support standard operators** — at minimum: `equals`, `in`, `greaterThan`, `lessThan`, `orderBy`, `limit`, `offset`.

---

## When NOT to Implement This Interface

Skip `DatastoreHasWhere` if:
* Your storage doesn't support filtering (e.g., simple key-value stores).
* Queries are always simple and `get()` suffices.
* You're wrapping an API that doesn't support complex queries.

---

## What's Next

* [Datastore Interface](/packages/datastore/interfaces/datastore) — the base interface
* [DatastoreHasCounts](/packages/datastore/interfaces/datastore-has-counts) — counting query results
* [WithDatastoreWhereDecorator](/packages/datastore/traits/with-datastore-where-decorator) — auto-implement this interface
