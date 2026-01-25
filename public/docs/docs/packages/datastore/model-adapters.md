# Model Adapters

Model adapters are **bidirectional transformers** that convert between your immutable domain models and storage-friendly associative arrays. They sit at the boundary between your business logic (which works with strongly-typed models) and your storage layer (which works with raw data).

Every handler needs an adapter to function. Without adapters, handlers wouldn't know how to convert database rows into models or how to extract data from models for persistence.

## The Adapter Contract

All adapters implement the `ModelAdapter` interface:

```php
interface ModelAdapter
{
    /**
     * Converts a model to an associative array for storage.
     *
     * @param DataModel $model The model to convert
     * @return array Associative array with storage-friendly keys
     */
    public function toArray(DataModel $model): array;

    /**
     * Converts an associative array from storage to a model.
     *
     * @param array $array Data from storage
     * @return DataModel The constructed model instance
     */
    public function toModel(array $array): DataModel;
}
```

This contract defines two operations:

* **`toArray()`** — Model → Array (for writes: `save()`, `update()`)
* **`toModel()`** — Array → Model (for reads: `get()`, `find()`)

---

## Basic Adapter Example

Here's a complete adapter for a `Post` model:

```php
<?php

namespace App\Core\Models\Adapters;

use PHPNomad\Datastore\Interfaces\DataModel;
use PHPNomad\Datastore\Interfaces\ModelAdapter;
use PHPNomad\Utils\Helpers\Arr;
use App\Core\Models\Post;

class PostAdapter implements ModelAdapter
{
    /**
     * Convert Post model to array for storage
     */
    public function toArray(DataModel $model): array
    {
        return [
            'id' => $model->id,
            'title' => $model->title,
            'content' => $model->content,
            'author_id' => $model->authorId,
            'published_date' => $model->publishedDate?->format('Y-m-d H:i:s'),
            'created_at' => $model->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $model->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Convert array from storage to Post model
     */
    public function toModel(array $array): DataModel
    {
        return new Post(
            id: Arr::get($array, 'id'),
            title: Arr::get($array, 'title', ''),
            content: Arr::get($array, 'content', ''),
            authorId: Arr::get($array, 'author_id'),
            publishedDate: Arr::get($array, 'published_date') 
                ? new \DateTime(Arr::get($array, 'published_date'))
                : null,
            createdAt: Arr::get($array, 'created_at')
                ? new \DateTime(Arr::get($array, 'created_at'))
                : null,
            updatedAt: Arr::get($array, 'updated_at')
                ? new \DateTime(Arr::get($array, 'updated_at'))
                : null
        );
    }
}
```

**Key responsibilities:**

1. **Field mapping** — Convert property names (camelCase) to column names (snake_case)
2. **Type conversion** — Transform `DateTime` objects to strings and back
3. **Default values** — Provide fallbacks for missing data using `Arr::get()`
4. **Null handling** — Handle nullable fields gracefully

---

## Why Adapters Exist

Without adapters, your handler would need to know how to construct your models:

```php
// ❌ BAD: handler knows model internals
class PostHandler
{
    public function find(int $id): Post
    {
        $row = $this->queryBuilder->select('*')->where('id', $id)->first();
        
        // Handler is tightly coupled to Post constructor
        return new Post(
            $row['id'],
            $row['title'] ?? '',
            $row['content'] ?? '',
            $row['author_id'],
            $row['published_date'] ? new DateTime($row['published_date']) : null
        );
    }
}
```

With adapters, handlers are **decoupled** from model details:

```php
// ✅ GOOD: handler delegates to adapter
class PostHandler
{
    public function __construct(
        private PostAdapter $adapter
    ) {}

    public function find(int $id): Post
    {
        $row = $this->queryBuilder->select('*')->where('id', $id)->first();
        return $this->adapter->toModel($row);  // Adapter handles construction
    }
}
```

Now if the `Post` constructor changes, only the adapter needs updating.

---

## Adapter Usage in Handlers

Handlers use adapters in both directions:

### Reading: Array → Model

When fetching data from storage:

```php
public function get(array $args = []): iterable
{
    $rows = $this->queryBuilder
        ->select('*')
        ->from($this->table->getTableName())
        ->where($args)
        ->getResults();

    // Convert each row to a model
    return array_map(
        fn($row) => $this->adapter->toModel($row),
        $rows
    );
}
```

### Writing: Model → Array

When persisting data to storage:

```php
public function save(DataModel $model): DataModel
{
    // Convert model to array
    $data = $this->adapter->toArray($model);

    if ($model->getId()) {
        // UPDATE
        $this->queryBuilder
            ->update($this->table->getTableName())
            ->set($data)
            ->where('id', $model->getId())
            ->execute();
    } else {
        // INSERT
        $id = $this->queryBuilder
            ->insert($this->table->getTableName())
            ->values($data)
            ->execute();
        
        // Return model with new ID
        $data['id'] = $id;
    }

    return $this->adapter->toModel($data);
}
```

---

## Handling Complex Types

Adapters often need to transform between domain types and storage primitives.

### DateTime Conversion

```php
public function toArray(DataModel $model): array
{
    return [
        'published_date' => $model->publishedDate?->format('Y-m-d H:i:s'),
    ];
}

public function toModel(array $array): DataModel
{
    return new Post(
        publishedDate: Arr::get($array, 'published_date')
            ? new \DateTime(Arr::get($array, 'published_date'))
            : null
    );
}
```

### JSON Fields

```php
public function toArray(DataModel $model): array
{
    return [
        'metadata' => json_encode($model->metadata),
    ];
}

public function toModel(array $array): DataModel
{
    return new Post(
        metadata: json_decode(Arr::get($array, 'metadata', '{}'), true)
    );
}
```

### Enums (PHP 8.1+)

```php
public function toArray(DataModel $model): array
{
    return [
        'status' => $model->status->value,  // Enum to string
    ];
}

public function toModel(array $array): DataModel
{
    return new Post(
        status: PostStatus::from(Arr::get($array, 'status', 'draft'))
    );
}
```

---

## Compound Identity Adapters

For models with compound primary keys, adapters handle multiple identifying fields:

```php
<?php

namespace App\Core\Models\Adapters;

use PHPNomad\Datastore\Interfaces\DataModel;
use PHPNomad\Datastore\Interfaces\ModelAdapter;
use PHPNomad\Utils\Helpers\Arr;
use App\Core\Models\UserSession;

class UserSessionAdapter implements ModelAdapter
{
    public function toArray(DataModel $model): array
    {
        return [
            'user_id' => $model->userId,
            'session_token' => $model->sessionToken,
            'ip_address' => $model->ipAddress,
            'expires_at' => $model->expiresAt->format('Y-m-d H:i:s'),
            'created_at' => $model->createdAt->format('Y-m-d H:i:s'),
        ];
    }

    public function toModel(array $array): DataModel
    {
        return new UserSession(
            userId: Arr::get($array, 'user_id'),
            sessionToken: Arr::get($array, 'session_token'),
            ipAddress: Arr::get($array, 'ip_address', ''),
            expiresAt: new \DateTime(Arr::get($array, 'expires_at')),
            createdAt: new \DateTime(Arr::get($array, 'created_at'))
        );
    }
}
```

---

## Field Name Mapping

Adapters bridge naming conventions between your domain (camelCase) and storage (snake_case):

**Domain model:**
```php
class Post
{
    public readonly int $authorId;
    public readonly DateTime $publishedDate;
}
```

**Database columns:**
```sql
CREATE TABLE posts (
    author_id BIGINT NOT NULL,
    published_date DATETIME
);
```

**Adapter mapping:**
```php
public function toArray(DataModel $model): array
{
    return [
        'author_id' => $model->authorId,        // camelCase → snake_case
        'published_date' => $model->publishedDate->format('Y-m-d H:i:s'),
    ];
}

public function toModel(array $array): DataModel
{
    return new Post(
        authorId: Arr::get($array, 'author_id'),  // snake_case → camelCase
        publishedDate: new DateTime(Arr::get($array, 'published_date'))
    );
}
```

---

## Default Values and Safety

Use `Arr::get()` with defaults to handle missing or null data gracefully:

```php
public function toModel(array $array): DataModel
{
    return new Post(
        id: Arr::get($array, 'id'),              // Required
        title: Arr::get($array, 'title', ''),    // Default to empty string
        content: Arr::get($array, 'content', ''),
        authorId: Arr::get($array, 'author_id', 0),
        publishedDate: Arr::get($array, 'published_date') 
            ? new DateTime(Arr::get($array, 'published_date'))
            : null,  // Nullable field
    );
}
```

This prevents crashes when data is incomplete or malformed.

---

## Adapters for Junction Tables

Junction table adapters are simpler—they only handle foreign keys:

```php
<?php

namespace App\Core\Models\Adapters;

use PHPNomad\Datastore\Interfaces\DataModel;
use PHPNomad\Datastore\Interfaces\ModelAdapter;
use PHPNomad\Utils\Helpers\Arr;
use App\Core\Models\PostTag;

class PostTagAdapter implements ModelAdapter
{
    public function toArray(DataModel $model): array
    {
        return [
            'post_id' => $model->postId,
            'tag_id' => $model->tagId,
        ];
    }

    public function toModel(array $array): DataModel
    {
        return new PostTag(
            postId: Arr::get($array, 'post_id'),
            tagId: Arr::get($array, 'tag_id')
        );
    }
}
```

---

## Testing Adapters

Adapters should be tested independently to ensure correct transformations:

```php
class PostAdapterTest extends TestCase
{
    private PostAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new PostAdapter();
    }

    public function test_toArray_converts_model_to_array(): void
    {
        $post = new Post(
            id: 1,
            title: 'Test Post',
            content: 'Content',
            authorId: 123,
            publishedDate: new DateTime('2024-01-01 12:00:00')
        );

        $array = $this->adapter->toArray($post);

        $this->assertEquals(1, $array['id']);
        $this->assertEquals('Test Post', $array['title']);
        $this->assertEquals('2024-01-01 12:00:00', $array['published_date']);
    }

    public function test_toModel_converts_array_to_model(): void
    {
        $array = [
            'id' => 1,
            'title' => 'Test Post',
            'content' => 'Content',
            'author_id' => 123,
            'published_date' => '2024-01-01 12:00:00',
        ];

        $post = $this->adapter->toModel($array);

        $this->assertEquals(1, $post->id);
        $this->assertEquals('Test Post', $post->title);
        $this->assertEquals(123, $post->authorId);
        $this->assertEquals('2024-01-01', $post->publishedDate->format('Y-m-d'));
    }

    public function test_roundtrip_preserves_data(): void
    {
        $original = new Post(
            id: 1,
            title: 'Test',
            content: 'Content',
            authorId: 123,
            publishedDate: new DateTime('2024-01-01')
        );

        $array = $this->adapter->toArray($original);
        $restored = $this->adapter->toModel($array);

        $this->assertEquals($original->id, $restored->id);
        $this->assertEquals($original->title, $restored->title);
    }
}
```

---

## Best Practices

### Use Arr::get() for Safe Access

```php
// ✅ GOOD: safe with defaults
$title = Arr::get($array, 'title', '');

// ❌ BAD: crashes if key missing
$title = $array['title'];
```

### Handle Null Appropriately

```php
// ✅ GOOD: null-safe
'published_date' => $model->publishedDate?->format('Y-m-d H:i:s')

// ❌ BAD: crashes if null
'published_date' => $model->publishedDate->format('Y-m-d H:i:s')
```

### Keep Adapters Pure

Adapters should only transform data—no business logic, no validation, no side effects:

```php
// ❌ BAD: adapter has business logic
public function toModel(array $array): DataModel
{
    $post = new Post(...);
    
    if ($post->publishedDate < new DateTime()) {
        throw new ValidationException("Cannot create past-dated post");
    }
    
    return $post;
}

// ✅ GOOD: adapter only transforms
public function toModel(array $array): DataModel
{
    return new Post(...);
}
```

### One Adapter Per Model

Each model should have exactly one adapter. Don't create multiple adapters for different serialization formats—use separate transformation services for that.

---

## What's Next

* [Integration Guide](/packages/datastore/integration-guide) — complete datastore setup with adapters
* [Models and Identity](/core-concepts/models-and-identity) — designing models for use with adapters
* [Database Handlers](/packages/database/handlers/introduction) — how handlers use adapters
