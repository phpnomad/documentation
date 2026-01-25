# DatastoreHasCounts Interface

The `DatastoreHasCounts` interface extends [`Datastore`](/packages/datastore/interfaces/datastore) to add **efficient counting operations**. It provides the `count()` method for determining how many records match given criteria without fetching and loading all the data.

This interface is useful for **pagination** (knowing total pages), **dashboard metrics** (e.g., "23 unread messages"), and **existence checks** (e.g., "are there any drafts?").

## Interface Definition

```php
interface DatastoreHasCounts extends Datastore
{
    /**
     * Returns the total number of records matching the criteria.
     *
     * @param array $args Filtering criteria (same format as get())
     * @return int The count of matching records
     */
    public function count(array $args = []): int;
}
```

## Method

### `count(array $args = []): int`

Counts records matching the provided criteria without fetching them.

**Parameters:**
* `$args` — Filtering criteria as an associative array (same format as `get()`).

**Returns:**
* An integer representing the number of matching records.

**When to use:**
* Calculating pagination totals
* Dashboard metrics and statistics
* Checking if records exist (`count() > 0`)
* Avoiding memory overhead of loading large result sets

**Example: total records**

```php
$totalPosts = $postDatastore->count();
echo "Total posts: {$totalPosts}";
```

**Example: filtered count**

```php
$publishedCount = $postDatastore->count(['status' => 'published']);
$draftCount = $postDatastore->count(['status' => 'draft']);

echo "Published: {$publishedCount}, Drafts: {$draftCount}";
```

**Example: existence check**

```php
$hasDrafts = $postDatastore->count(['status' => 'draft']) > 0;

if ($hasDrafts) {
    echo "You have unpublished drafts";
}
```

---

## Why This Interface Exists

Without `count()`, you'd have to fetch all records and count them:

```php
// ❌ BAD: loads all records into memory
$posts = $datastore->get(['author_id' => 123]);
$count = count($posts); // Expensive!
```

With `count()`, the operation happens at the storage layer:

```php
// ✅ GOOD: efficient database COUNT query
$count = $datastore->count(['author_id' => 123]);
```

For databases, this translates to `SELECT COUNT(*) FROM ...`, which is far more efficient than fetching rows.

---

## Usage Patterns

### Pagination

Counting is essential for calculating total pages:

```php
final class PostPaginationService
{
    public function __construct(
        private PostDatastore $posts
    ) {}

    public function getPaginationInfo(array $filters, int $perPage): array
    {
        $total = $this->posts->count($filters);
        $totalPages = (int) ceil($total / $perPage);

        return [
            'total' => $total,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    public function getPage(array $filters, int $page, int $perPage): iterable
    {
        return $this->posts->get(array_merge($filters, [
            'limit' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ]));
    }
}
```

**Usage:**

```php
$filters = ['author_id' => 123, 'status' => 'published'];

$info = $service->getPaginationInfo($filters, perPage: 10);
// ['total' => 47, 'per_page' => 10, 'total_pages' => 5]

$posts = $service->getPage($filters, page: 2, perPage: 10);
// Returns posts 11-20
```

### Dashboard Metrics

Counting is ideal for statistics dashboards:

```php
final class DashboardService
{
    public function __construct(
        private PostDatastore $posts
    ) {}

    public function getStats(int $authorId): array
    {
        return [
            'total' => $this->posts->count(['author_id' => $authorId]),
            'published' => $this->posts->count([
                'author_id' => $authorId,
                'status' => 'published'
            ]),
            'drafts' => $this->posts->count([
                'author_id' => $authorId,
                'status' => 'draft'
            ]),
        ];
    }
}
```

**Returns:**

```php
[
    'total' => 52,
    'published' => 48,
    'drafts' => 4,
]
```

### Conditional Logic

Use `count()` for existence checks or thresholds:

```php
// Check if user has any posts before allowing account deletion
$postCount = $postDatastore->count(['author_id' => $userId]);

if ($postCount > 0) {
    throw new ValidationException("Cannot delete user with existing posts");
}

// Enforce post limits
$userPostCount = $postDatastore->count(['author_id' => $userId]);

if ($userPostCount >= 100) {
    throw new LimitExceededException("Post limit reached");
}
```

---

## Combining with WHERE Queries

If your datastore implements both `DatastoreHasCounts` and [`DatastoreHasWhere`](/packages/datastore/interfaces/datastore-has-where), you can count complex queries:

```php
$query = $postDatastore
    ->where()
    ->equals('author_id', 123)
    ->greaterThan('published_date', '2024-01-01')
    ->lessThan('view_count', 100);

$count = $query->count(); // How many match?
$posts = $query->getResults(); // Fetch them if needed
```

This is more powerful than `count($args)` because you get the full query-builder API.

---

## Relationship to Other Interfaces

### vs. `get()` + `count()`

| Method | Efficiency | Use Case |
|--------|-----------|----------|
| `count($args)` | High (storage-layer count) | Pagination, metrics, existence checks |
| `count(get($args))` | Low (loads all data) | Never do this |

**Always use `count()` instead of loading and counting.**

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

This provides a complete query API.

---

## Implementation with Decorator Traits

Use [`WithDatastoreCountDecorator`](/packages/datastore/traits/with-datastore-count-decorator) to auto-implement:

```php
final class PostDatastoreImpl implements DatastoreHasCounts
{
    use WithDatastoreCountDecorator;

    public function __construct(
        private DatastoreHandlerHasCounts $handler
    ) {}
}
```

The trait delegates `count()` to the handler.

---

## Implementation Notes

When implementing this interface:

* **`count()` should be efficient** — execute a storage-layer count (e.g., `SELECT COUNT(*)`), not fetch-and-count.
* **Return 0 for no matches** — don't return `null` or throw exceptions.
* **`$args` format matches `get()`** — use the same filtering conventions.
* **Support empty args** — `count()` with no args returns the total record count.

---

## When NOT to Implement This Interface

Skip `DatastoreHasCounts` if:
* Your storage can't count efficiently (e.g., some APIs don't expose count endpoints).
* Counting isn't needed for your use case.
* You're prototyping and can add it later.

---

## What's Next

* [Datastore Interface](/packages/datastore/interfaces/datastore) — the base interface
* [DatastoreHasWhere](/packages/datastore/interfaces/datastore-has-where) — query-builder counting
* [WithDatastoreCountDecorator](/packages/datastore/traits/with-datastore-count-decorator) — auto-implement this interface
