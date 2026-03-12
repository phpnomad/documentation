# Models and Identity

## What are models?

Models are immutable value objects that represent domain entities in your application. They contain data and domain
logic but have no knowledge of how they are persisted. A model never saves itself, queries a database, or makes API
calls—it is purely a container for data with behavior.

Models are independent of storage. Whether data comes from a database, REST API, or cache, the model remains the same.
This separation keeps domain logic clean and portable.

---

## The DataModel interface

All models must implement the `DataModel` interface, which marks them as domain entities that can be stored and
retrieved through datastores:

```php
interface DataModel
{
    public function getIdentity(): array;
}
```

The `getIdentity()` method returns an associative array representing how the entity is uniquely identified. For a Post
with ID 123, this might return `['id' => 123]`. For a UserSession identified by both user ID and session token, it
returns `['userId' => 456, 'sessionToken' => 'abc123']`.

Datastores use this identity array to look up, update, and delete specific entities.

---

## Understanding identity

Identity determines how entities are uniquely identified. There are two primary patterns:

### Single integer identity

Most entities use a single auto-incrementing integer as their primary identifier. Examples include posts, users,
products, and orders.

For these entities, implement the `HasSingleIntIdentity` interface:

```php
interface HasSingleIntIdentity
{
    public function getId(): int;
    public function getIdentity(): array;
}
```

This interface requires both a `getId()` method that returns the integer ID, and a `getIdentity()` method that returns
`['id' => $this->getId()]`.

### Compound identity

Some entities require multiple values to be uniquely identified. This happens when entities use composite keys in the
database. Common examples:

- **User sessions** - identified by `userId` + `sessionToken`
- **Translations** - identified by `entityId` + `locale`
- **Time-series data** - identified by `deviceId` + `timestamp`
- **Versioned content** - identified by `contentId` + `version`

For these entities, `getIdentity()` returns an array with multiple keys:

```php
public function getIdentity(): array
{
    return [
        'userId' => $this->userId,
        'sessionToken' => $this->sessionToken
    ];
}
```

The keys in this array must match the columns used to uniquely identify records in storage.

---

## Single integer identity pattern

For entities with a single integer ID, use the `WithSingleIntIdentity` trait to reduce boilerplate.

### Using WithSingleIntIdentity trait

The `WithSingleIntIdentity` trait provides:

- A protected `$id` property
- Implementation of `getId()` that returns the ID
- Implementation of `getIdentity()` that returns `['id' => $this->getId()]`

Example:

```php
<?php

namespace Blog\Core\Models;

use Nomad\Datastore\Interfaces\DataModel;
use Nomad\Datastore\Interfaces\HasSingleIntIdentity;
use Nomad\Datastore\Traits\WithSingleIntIdentity;
use DateTime;

class Post implements DataModel, HasSingleIntIdentity
{
    use WithSingleIntIdentity;

    public function __construct(
        int $id,
        public readonly string $title,
        public readonly string $content,
        public readonly int $authorId,
        public readonly DateTime $publishedDate
    ) {
        $this->id = $id;
    }
}

// Usage:
$post = new Post(123, 'My Post', 'Content...', 1, new DateTime());
echo $post->getId(); // 123
print_r($post->getIdentity()); // ['id' => 123]
```

### Manual implementation

If you prefer not to use the trait, implement the interface manually:

```php
class Post implements DataModel, HasSingleIntIdentity
{
    public function __construct(
        private int $id,
        public readonly string $title,
        public readonly string $content
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getIdentity(): array
    {
        return ['id' => $this->id];
    }
}
```

The trait is recommended to keep implementations consistent across your codebase.

---

## Compound identity pattern

For entities with compound keys, implement `getIdentity()` to return all identifying values.

Example with UserSession:

```php
<?php

namespace Auth\Core\Models;

use Nomad\Datastore\Interfaces\DataModel;
use DateTime;

class UserSession implements DataModel
{
    public function __construct(
        public readonly int $userId,
        public readonly string $sessionToken,
        public readonly DateTime $expiresAt,
        public readonly string $ipAddress
    ) {}

    public function getIdentity(): array
    {
        return [
            'userId' => $this->userId,
            'sessionToken' => $this->sessionToken
        ];
    }
}
```

When the datastore performs operations on this entity, it uses both `userId` and `sessionToken` to identify the record:

```php
// Datastore uses compound identity for lookups
$session = $sessionDatastore->findCompound([
    'userId' => 456,
    'sessionToken' => 'abc123'
]);

// Update uses compound identity
$sessionDatastore->updateCompound(
    ['userId' => 456, 'sessionToken' => 'abc123'],
    ['ipAddress' => '192.168.1.1']
);
```

The keys in the identity array must exactly match the column names used in your storage implementation.

---

## Timestamp traits

PHPNomad provides traits for automatic timestamp tracking.

### WithCreatedDate trait

The `WithCreatedDate` trait provides:

- A protected `$createdDate` property of type `?DateTime`
- Implementation of `getCreatedDate()` that returns the creation timestamp
- Automatic handling of null values for new entities

Example:

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
        ?DateTime $createdDate = null
    ) {
        $this->id = $id;
        $this->createdDate = $createdDate;
    }
}

// Usage:
$post = new Post(123, 'Title', 'Content', new DateTime('2025-01-08 10:00:00'));
echo $post->getCreatedDate()->format('Y-m-d H:i:s'); // 2025-01-08 10:00:00
```

When creating new entities, pass `null` for `createdDate`. The database will set it automatically via
`DEFAULT CURRENT_TIMESTAMP`.

### WithModifiedDate trait

The `WithModifiedDate` trait works similarly for tracking last modification time:

```php
use Nomad\Datastore\Traits\WithModifiedDate;

class Post implements DataModel, HasSingleIntIdentity
{
    use WithSingleIntIdentity;
    use WithCreatedDate;
    use WithModifiedDate;

    public function __construct(
        int $id,
        public readonly string $title,
        ?DateTime $createdDate = null,
        ?DateTime $modifiedDate = null
    ) {
        $this->id = $id;
        $this->createdDate = $createdDate;
        $this->modifiedDate = $modifiedDate;
    }
}

// Usage:
echo $post->getModifiedDate()?->format('Y-m-d H:i:s');
```

The database automatically updates `modifiedDate` via `ON UPDATE CURRENT_TIMESTAMP` when using the corresponding table
column factory.

### When to use traits vs manual implementation

**Use traits when:**

- You want consistent timestamp handling across entities
- The standard implementation (nullable DateTime) fits your needs
- You want to reduce boilerplate code

**Implement manually when:**

- You need custom timestamp logic
- You require non-nullable timestamps with specific defaults
- You want to calculate timestamps based on other model data

---

## DateTime handling in models

Models use PHP `DateTime` objects for all date and time values. Adapters handle conversion between `DateTime` objects
and database string formats.

### Model with DateTime properties:

```php
class Post implements DataModel, HasSingleIntIdentity
{
    use WithSingleIntIdentity;

    public function __construct(
        int $id,
        public readonly DateTime $publishedDate,
        public readonly ?DateTime $scheduledDate = null
    ) {
        $this->id = $id;
    }
}
```

### Adapter converts DateTime to/from strings:

```php
class PostAdapter implements ModelAdapter
{
    public function __construct(
        private DateFormatterService $dateFormatterService
    ) {}

    public function toModel(array $data): Post
    {
        return new Post(
            id: $data['id'],
            publishedDate: $this->dateFormatterService->getDateTime(
                $data['publishedDate']
            ),
            scheduledDate: $this->dateFormatterService->getDateTimeOrNull(
                $data['scheduledDate']
            )
        );
    }

    public function toArray(Post $model): array
    {
        return [
            'id' => $model->getId(),
            'publishedDate' => $this->dateFormatterService->getDateString(
                $model->publishedDate
            ),
            'scheduledDate' => $this->dateFormatterService->getDateStringOrNull(
                $model->scheduledDate
            ),
        ];
    }
}
```

**Key points:**

- Models always use `DateTime` objects, never strings
- `DateFormatterService` handles conversion to database format (usually `Y-m-d H:i:s`)
- Use nullable `?DateTime` for optional dates
- Adapters use `getDateTime()` for required dates, `getDateTimeOrNull()` for optional dates

---

## Model immutability

Models must be immutable—their state cannot change after construction. This prevents bugs, simplifies reasoning about
code, and enables safe caching.

### Correct immutable model:

```php
class Post implements DataModel, HasSingleIntIdentity
{
    use WithSingleIntIdentity;

    public function __construct(
        int $id,
        public readonly string $title,
        public readonly string $content,
        public readonly bool $published
    ) {
        $this->id = $id;
    }
}
```

### Common mistakes - DO NOT DO THIS:

```php
// WRONG: Mutable properties
class Post implements DataModel, HasSingleIntIdentity
{
    use WithSingleIntIdentity;

    public function __construct(
        int $id,
        public string $title  // Not readonly - can be changed!
    ) {
        $this->id = $id;
    }
}

// WRONG: Setter methods
class Post implements DataModel, HasSingleIntIdentity
{
    use WithSingleIntIdentity;

    public function __construct(
        int $id,
        public readonly string $title
    ) {
        $this->id = $id;
    }

    // NEVER add setters!
    public function setTitle(string $title): void
    {
        $this->title = $title; // Breaks immutability!
    }
}
```

### Why immutability matters:

**Prevents bugs:**

```php
$post = $datastore->find(123);
$cachedPost = $cache->get('post:123');

// If models were mutable, changing one affects the other
$post->title = 'New Title'; // Would corrupt cache!
```

**Enables safe caching:**

```php
// Datastore can safely cache immutable models
$post = $datastore->find(123); // Caches result
$samePost = $datastore->find(123); // Returns cached instance
// Both references point to same object, but it can't be changed
```

**Simplifies concurrency:**

```php
// Multiple threads can safely read the same model
// No locks or synchronization needed
```

### How to "update" immutable models:

You don't modify existing models—you create new ones with changed values:

```php
// Get existing post
$post = $datastore->find(123);

// Create updated post by creating new instance
$updatedPost = new Post(
    id: $post->getId(),
    title: 'New Title',  // Changed
    content: $post->content,  // Same
    published: $post->published  // Same
);

// Or use datastore update, which creates a new instance internally
$datastore->update(123, ['title' => 'New Title']);
```

The datastore handles updates by:

1. Loading the current model
2. Merging changes from the array
3. Creating a new model instance
4. Persisting the new state
5. Returning the new model

---

## Model best practices

### Use public readonly properties

Constructor property promotion with `readonly` provides immutability and clean syntax:

```php
class Post implements DataModel, HasSingleIntIdentity
{
    use WithSingleIntIdentity;

    public function __construct(
        int $id,
        public readonly string $title,
        public readonly string $content,
        public readonly int $authorId
    ) {
        $this->id = $id;
    }
}

// Access directly, no getters needed
echo $post->title;
echo $post->authorId;
```

### Models are for data access only

**Models should never contain business logic.** They are purely data containers with no behavior beyond providing access to their properties.

**DO NOT add methods like:**
- `isExpired()` - belongs in a service
- `isPublished()` - belongs in a service
- `calculateTotal()` - belongs in a service
- `validate()` - belongs in a service or validator
- `canBeEditedBy()` - belongs in an authorization service

```php
// WRONG - business logic in model
class Post implements DataModel, HasSingleIntIdentity
{
    use WithSingleIntIdentity;

    public function __construct(
        int $id,
        public readonly DateTime $publishedDate
    ) {
        $this->id = $id;
    }

    // DON'T DO THIS
    public function isPublished(): bool
    {
        return $this->publishedDate <= new DateTime();
    }
}

// CORRECT - model is data only
class Post implements DataModel, HasSingleIntIdentity
{
    use WithSingleIntIdentity;

    public function __construct(
        int $id,
        public readonly DateTime $publishedDate
    ) {
        $this->id = $id;
    }
}

// Business logic belongs in services
class PostService
{
    public function isPublished(Post $post): bool
    {
        return $post->publishedDate <= new DateTime();
    }
}
```

Models are designed to be serializable, cacheable, and transferable. Business logic in models creates coupling and makes them harder to test and maintain. Keep models as simple data structures and put all logic in services.

### Handle relationships with IDs

If your entity relates to other entities, store IDs rather than embedding objects:

```php
class Post implements DataModel, HasSingleIntIdentity
{
    use WithSingleIntIdentity;

    public function __construct(
        int $id,
        public readonly string $title,
        public readonly int $authorId  // Store ID, not Author object
    ) {
        $this->id = $id;
    }
}

// Fetch related entities separately through their datastores
$post = $postDatastore->find(123);
$author = $authorDatastore->find($post->authorId);
```

This keeps models simple and storage-agnostic.

---

## Complete examples

### Simple entity with single ID:

```php
<?php

namespace Blog\Core\Models;

use Nomad\Datastore\Interfaces\DataModel;
use Nomad\Datastore\Interfaces\HasSingleIntIdentity;
use Nomad\Datastore\Traits\WithSingleIntIdentity;
use Nomad\Datastore\Traits\WithCreatedDate;
use DateTime;

class Author implements DataModel, HasSingleIntIdentity
{
    use WithSingleIntIdentity;
    use WithCreatedDate;

    public function __construct(
        int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly bool $active,
        ?DateTime $createdDate = null
    ) {
        $this->id = $id;
        $this->createdDate = $createdDate;
    }
}
```

### Entity with compound identity:

```php
<?php

namespace Auth\Core\Models;

use Nomad\Datastore\Interfaces\DataModel;
use Nomad\Datastore\Traits\WithCreatedDate;
use DateTime;

class UserSession implements DataModel
{
    use WithCreatedDate;

    public function __construct(
        public readonly int $userId,
        public readonly string $sessionToken,
        public readonly DateTime $expiresAt,
        public readonly string $ipAddress,
        public readonly string $userAgent,
        ?DateTime $createdDate = null
    ) {
        $this->createdDate = $createdDate;
    }

    public function getIdentity(): array
    {
        return [
            'userId' => $this->userId,
            'sessionToken' => $this->sessionToken
        ];
    }
}
```

---

## Summary

Models are immutable value objects that represent domain entities. They implement the `DataModel` interface and provide
identity through `getIdentity()`. Use `WithSingleIntIdentity` for entities with single integer IDs, or implement
compound identity for entities requiring multiple identifying values. Models use `DateTime` for dates, with adapters
handling string conversion. Traits like `WithCreatedDate` and `WithModifiedDate` provide automatic timestamp tracking.
Always use `public readonly` properties to enforce immutability. Models must never contain business logic—they are purely data containers. All business logic belongs in services.
