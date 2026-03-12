# Core Datastore Layer

## What is the Core layer?

The Core layer is where you define business-level data operations without any knowledge of how data is actually stored or retrieved. It contains interfaces that declare what operations are possible, and implementations that delegate standard operations while adding custom business logic.

Core never depends on Service. It knows nothing about databases, REST APIs, GraphQL, or any concrete storage technology. This separation ensures your domain logic remains portable and independent of infrastructure.

The Core layer contains:
- **Datastore interfaces** - Public API for your application code
- **DatastoreHandler interfaces** - Contract for storage implementations
- **Datastore implementations** - Delegation layer using decorator pattern
- **Models** - Domain entities (covered in [Models and Identity](models-and-identity))

---

## Directory structure

The standard directory structure for Core datastores:

```
YourModule/
└── Core/
    ├── Models/
    │   ├── Post.php
    │   └── Adapters/
    │       └── PostAdapter.php
    └── Datastores/
        └── Post/
            ├── Interfaces/
            │   ├── PostDatastore.php
            │   └── PostDatastoreHandler.php
            └── PostDatastore.php
```

**Key points:**
- Each entity gets its own directory under `Datastores/`
- Interfaces live in `Interfaces/` subdirectory
- Implementation lives at the entity directory level
- Models and adapters are separate from datastores

---

## Naming conventions

Consistent naming makes codebases predictable and maintainable:

| Component | Pattern | Example |
|-----------|---------|---------|
| Datastore interface | `{Entity}Datastore` | `PostDatastore` |
| DatastoreHandler interface | `{Entity}DatastoreHandler` | `PostDatastoreHandler` |
| Datastore implementation | `{Entity}Datastore` | `PostDatastore` |
| Model | `{Entity}` | `Post` |
| Adapter | `{Entity}Adapter` | `PostAdapter` |

**Important:** The Datastore interface and implementation share the same name. They are distinguished by namespace and the interface suffix in the interface file.

---

## Datastore vs DatastoreHandler: The critical distinction

This is the most confusing aspect of the datastore pattern. Understanding why both interfaces exist is essential to using the pattern effectively.

### PostDatastore: Your public API

The `Datastore` interface defines your **public API**—what operations your application code can perform. This interface includes:

- Standard operations (if you choose to extend base interfaces)
- Custom business methods specific to this entity

```php
<?php

namespace Blog\Core\Datastores\Post\Interfaces;

use Blog\Core\Models\Post;
use Nomad\Datastore\Interfaces\Datastore;
use Nomad\Datastore\Interfaces\DatastoreHasPrimaryKey;
use Nomad\Datastore\Interfaces\DatastoreHasWhere;

interface PostDatastore extends Datastore, DatastoreHasPrimaryKey, DatastoreHasWhere
{
    /**
     * Get all published posts.
     *
     * @return Post[]
     */
    public function getPublishedPosts(): array;

    /**
     * Get posts by a specific author.
     *
     * @param int $authorId
     * @return Post[]
     */
    public function getByAuthor(int $authorId): array;
}
```

This is what your controllers, services, and other application code depend on. This interface is your **contract with consumers**.

### PostDatastoreHandler: The implementation contract

The `DatastoreHandler` interface defines the **contract for storage implementations**. This is what database handlers, REST handlers, and GraphQL handlers implement.

```php
<?php

namespace Blog\Core\Datastores\Post\Interfaces;

use Nomad\Datastore\Interfaces\Datastore;
use Nomad\Datastore\Interfaces\DatastoreHasPrimaryKey;
use Nomad\Datastore\Interfaces\DatastoreHasWhere;

interface PostDatastoreHandler extends Datastore, DatastoreHasPrimaryKey, DatastoreHasWhere
{
    // Only extends base interfaces
    // NO custom business methods
}
```

**Critical difference:** The `DatastoreHandler` typically includes **only** standard interface operations. It does not include custom business methods like `getPublishedPosts()` or `getByAuthor()`.

### Why the separation?

The separation exists to distinguish between:

1. **What your application needs** (`PostDatastore`) - may include custom methods
2. **What storage implementations must provide** (`PostDatastoreHandler`) - usually just standard CRUD

Consider this scenario:

```php
// Your Datastore interface (public API)
interface PostDatastore extends Datastore, DatastoreHasPrimaryKey, DatastoreHasWhere
{
    public function getPublishedPosts(): array;
    public function getByAuthor(int $authorId): array;
}

// Your DatastoreHandler interface (implementation contract)
interface PostDatastoreHandler extends Datastore, DatastoreHasPrimaryKey, DatastoreHasWhere
{
    // Standard operations only
}

// Your Core implementation bridges them
class PostDatastoreConcrete implements PostDatastore
{
    use WithDatastoreDecorator;
    use WithDatastorePrimaryKeyDecorator;
    use WithDatastoreWhereDecorator;

    protected Datastore $datastoreHandler;

    public function __construct(PostDatastoreHandler $datastoreHandler)
    {
        $this->datastoreHandler = $datastoreHandler;
    }

    // Custom business method - implemented here, not in handler
    public function getPublishedPosts(): array
    {
        return $this->datastoreHandler->where([
            [
                'type' => 'AND',
                'clauses' => [
                    ['column' => 'publishedDate', 'operator' => '<=', 'value' => date('Y-m-d H:i:s')]
                ]
            ]
        ]);
    }

    public function getByAuthor(int $authorId): array
    {
        return $this->datastoreHandler->where([
            [
                'type' => 'AND',
                'clauses' => [
                    ['column' => 'authorId', 'operator' => '=', 'value' => $authorId]
                ]
            ]
        ]);
    }
}
```

The custom methods (`getPublishedPosts`, `getByAuthor`) are implemented in the Core datastore using the handler's `where()` method. The handler doesn't need to know about these business-specific queries—it just provides the building blocks.

**This means:**
- Database handlers, REST handlers, GraphQL handlers only need to implement standard operations
- Business logic lives in the Core datastore, composed from handler primitives
- You can swap storage implementations without changing business methods
- Each handler implementation doesn't need to understand your specific business domain

---

## When to extend base interfaces (and when not to)

The base datastore interfaces (`DatastoreHasPrimaryKey`, `DatastoreHasWhere`, etc.) provide standard operations. **You are not required to extend them.**

### Full standard interface (database-friendly)

If your storage supports queries, filtering, and standard CRUD, extend the base interfaces:

```php
interface PostDatastore extends Datastore, DatastoreHasPrimaryKey, DatastoreHasWhere, DatastoreHasCounts
{
    public function getPublishedPosts(): array;
}

interface PostDatastoreHandler extends Datastore, DatastoreHasPrimaryKey, DatastoreHasWhere, DatastoreHasCounts
{
    // Standard operations
}
```

**Use when:**
- Storage is a database with full query support
- You want standard CRUD operations available
- Consumers benefit from generic query methods like `where()`


## The decorator pattern with traits

When your `Datastore` and `DatastoreHandler` both extend the same base interfaces, use decorator traits to eliminate boilerplate delegation code.

### Without decorator traits (manual delegation)

Without traits, you'd write delegation methods for every standard operation:

```php
class PostDatastore implements PostDatastoreInterface
{
    protected Datastore $datastoreHandler;

    public function __construct(PostDatastoreHandler $datastoreHandler)
    {
        $this->datastoreHandler = $datastoreHandler;
    }

    // Manual delegation for Datastore methods
    public function create(array $attributes): Post
    {
        return $this->datastoreHandler->create($attributes);
    }

    public function updateCompound(array $ids, array $attributes): void
    {
        $this->datastoreHandler->updateCompound($ids, $attributes);
    }

    // Manual delegation for DatastoreHasPrimaryKey methods
    public function find(int $id): Post
    {
        return $this->datastoreHandler->find($id);
    }

    public function findMultiple(array $ids): array
    {
        return $this->datastoreHandler->findMultiple($ids);
    }

    public function update(int $id, array $attributes): void
    {
        $this->datastoreHandler->update($id, $attributes);
    }

    public function delete(int $id): void
    {
        $this->datastoreHandler->delete($id);
    }

    // Manual delegation for DatastoreHasWhere methods
    public function where(array $conditions, ?int $limit = null, ?int $offset = null, ?string $orderBy = null, string $order = 'ASC'): array
    {
        return $this->datastoreHandler->where($conditions, $limit, $offset, $orderBy, $order);
    }

    public function andWhere(array $conditions, ?int $limit = null, ?int $offset = null, ?string $orderBy = null, string $order = 'ASC'): array
    {
        return $this->datastoreHandler->andWhere($conditions, $limit, $offset, $orderBy, $order);
    }

    public function orWhere(array $conditions, ?int $limit = null, ?int $offset = null, ?string $orderBy = null, string $order = 'ASC'): array
    {
        return $this->datastoreHandler->orWhere($conditions, $limit, $offset, $orderBy, $order);
    }

    public function deleteWhere(array $conditions): void
    {
        $this->datastoreHandler->deleteWhere($conditions);
    }

    public function findBy(string $field, $value): Post
    {
        return $this->datastoreHandler->findBy($field, $value);
    }

    // Plus count methods, plus custom methods...
}
```

That's dozens of lines of boilerplate for a simple datastore.

### With decorator traits (automatic delegation)

Decorator traits handle all standard delegation automatically:

```php
class PostDatastore implements PostDatastoreInterface
{
    use WithDatastoreDecorator;              // Delegates: create, updateCompound
    use WithDatastorePrimaryKeyDecorator;     // Delegates: find, findMultiple, update, delete
    use WithDatastoreWhereDecorator;          // Delegates: where, andWhere, orWhere, deleteWhere, findBy
    use WithDatastoreCountDecorator;          // Delegates: count methods

    protected Datastore $datastoreHandler;

    public function __construct(PostDatastoreHandler $datastoreHandler)
    {
        $this->datastoreHandler = $datastoreHandler;
    }

    // Only implement custom business methods
    public function getPublishedPosts(): array
    {
        return $this->datastoreHandler->where([
            [
                'type' => 'AND',
                'clauses' => [
                    ['column' => 'publishedDate', 'operator' => '<=', 'value' => date('Y-m-d H:i:s')]
                ]
            ]
        ]);
    }

    public function getByAuthor(int $authorId): array
    {
        return $this->datastoreHandler->where([
            [
                'type' => 'AND',
                'clauses' => [
                    ['column' => 'authorId', 'operator' => '=', 'value' => $authorId]
                ]
            ]
        ]);
    }
}
```

All standard operations automatically delegate to `$this->datastoreHandler`. You only write custom business methods.

### Available decorator traits

| Trait | Delegates Methods |
|-------|-------------------|
| `WithDatastoreDecorator` | `create()`, `updateCompound()` |
| `WithDatastorePrimaryKeyDecorator` | `find()`, `findMultiple()`, `update()`, `delete()` |
| `WithDatastoreWhereDecorator` | `where()`, `andWhere()`, `orWhere()`, `deleteWhere()`, `findBy()` |
| `WithDatastoreCountDecorator` | Count-related methods |

Use the traits that match the interfaces your Datastore extends. If your `PostDatastore` extends `DatastoreHasPrimaryKey`, use `WithDatastorePrimaryKeyDecorator`.

### When NOT to use decorator traits

**Don't use decorator traits when:**

1. **Your interfaces don't match** - If `PostDatastore` extends `DatastoreHasWhere` but `PostDatastoreHandler` doesn't, you can't delegate
2. **You want a minimal API** - If you're not extending base interfaces, don't use delegation traits
3. **You need custom behavior** - If standard operations need special handling, implement them manually

```php
// Example: Minimal API, no delegation
interface PostDatastore extends Datastore
{
    public function getPublishedPosts(): array;
}

interface PostDatastoreHandler extends Datastore
{
    // Minimal
}

class PostDatastore implements PostDatastoreInterface
{
    // NO decorator traits

    public function __construct(
        private PostDatastoreHandler $datastoreHandler
    ) {}

    // Implement everything explicitly
    public function create(array $attributes): Post
    {
        return $this->datastoreHandler->create($attributes);
    }

    public function updateCompound(array $ids, array $attributes): void
    {
        $this->datastoreHandler->updateCompound($ids, $attributes);
    }

    public function getPublishedPosts(): array
    {
        // Custom implementation
    }
}
```

---

## Custom business methods

Custom methods define domain-specific operations. They use handler primitives to implement business logic.

### Pattern 1: Simple filtering

```php
interface PostDatastore extends Datastore, DatastoreHasWhere
{
    public function getPublishedPosts(): array;
    public function getDraftPosts(): array;
}

class PostDatastore implements PostDatastoreInterface
{
    use WithDatastoreDecorator;
    use WithDatastoreWhereDecorator;

    protected Datastore $datastoreHandler;

    public function __construct(PostDatastoreHandler $datastoreHandler)
    {
        $this->datastoreHandler = $datastoreHandler;
    }

    public function getPublishedPosts(): array
    {
        return $this->datastoreHandler->where([
            [
                'type' => 'AND',
                'clauses' => [
                    ['column' => 'status', 'operator' => '=', 'value' => 'published']
                ]
            ]
        ]);
    }

    public function getDraftPosts(): array
    {
        return $this->datastoreHandler->where([
            [
                'type' => 'AND',
                'clauses' => [
                    ['column' => 'status', 'operator' => '=', 'value' => 'draft']
                ]
            ]
        ]);
    }
}
```

### Pattern 2: Lookup by specific field

```php
interface PostDatastore extends Datastore, DatastoreHasWhere
{
    public function getBySlug(string $slug): Post;
    public function getByAuthor(int $authorId): array;
}

class PostDatastore implements PostDatastoreInterface
{
    use WithDatastoreDecorator;
    use WithDatastoreWhereDecorator;

    protected Datastore $datastoreHandler;

    public function __construct(PostDatastoreHandler $datastoreHandler)
    {
        $this->datastoreHandler = $datastoreHandler;
    }

    public function getBySlug(string $slug): Post
    {
        return $this->datastoreHandler->findBy('slug', $slug);
    }

    public function getByAuthor(int $authorId): array
    {
        return $this->datastoreHandler->where([
            [
                'type' => 'AND',
                'clauses' => [
                    ['column' => 'authorId', 'operator' => '=', 'value' => $authorId]
                ]
            ]
        ]);
    }
}
```

### Pattern 3: Complex queries

```php
interface PostDatastore extends Datastore, DatastoreHasWhere
{
    public function getRecentPublishedByAuthor(int $authorId, int $limit = 10): array;
}

class PostDatastore implements PostDatastoreInterface
{
    use WithDatastoreDecorator;
    use WithDatastoreWhereDecorator;

    protected Datastore $datastoreHandler;

    public function __construct(PostDatastoreHandler $datastoreHandler)
    {
        $this->datastoreHandler = $datastoreHandler;
    }

    public function getRecentPublishedByAuthor(int $authorId, int $limit = 10): array
    {
        return $this->datastoreHandler->where(
            conditions: [
                [
                    'type' => 'AND',
                    'clauses' => [
                        ['column' => 'authorId', 'operator' => '=', 'value' => $authorId],
                        ['column' => 'status', 'operator' => '=', 'value' => 'published']
                    ]
                ]
            ],
            limit: $limit,
            orderBy: 'publishedDate',
            order: 'DESC'
        );
    }
}
```

### Pattern 4: Combining multiple operations

```php
interface PostDatastore extends Datastore, DatastoreHasPrimaryKey, DatastoreHasWhere
{
    public function publishPost(int $postId): Post;
}

class PostDatastore implements PostDatastoreInterface
{
    use WithDatastoreDecorator;
    use WithDatastorePrimaryKeyDecorator;
    use WithDatastoreWhereDecorator;

    protected Datastore $datastoreHandler;

    public function __construct(PostDatastoreHandler $datastoreHandler)
    {
        $this->datastoreHandler = $datastoreHandler;
    }

    public function publishPost(int $postId): Post
    {
        $this->datastoreHandler->update($postId, [
            'status' => 'published',
            'publishedDate' => date('Y-m-d H:i:s')
        ]);

        return $this->datastoreHandler->find($postId);
    }
}
```

---

## Design principles for Core datastores

### Keep business logic in the Core implementation

Custom methods implement business logic by composing handler primitives. The handler doesn't know about "published posts" or "recent posts"—it just provides query capabilities. The Core datastore interprets what "published" means.

### Be intentional about your public API

Every method you add to `PostDatastore` is a promise to consumers. If you add `where()` to your interface, consumers will use it. If you later switch to a REST API that doesn't support generic queries, you'll break consumers.

Ask yourself:
- Will this storage always support this operation?
- Do I want consumers calling this directly?
- Is this operation stable long-term?

If unsure, keep your interface minimal and add methods as needed.

### Handler interfaces should be generic

The `DatastoreHandler` interface should contain only operations that **any storage implementation** can reasonably provide. Don't add business-specific methods to the handler—those belong in the `Datastore` implementation.

---

## Summary

The Core datastore layer defines business-level data operations through interfaces and implementations. The critical distinction is between `Datastore` (public API for consumers) and `DatastoreHandler` (contract for storage implementations). Decorator traits eliminate boilerplate delegation when both interfaces extend the same base interfaces. For tighter control or limited storage capabilities, opt out of base interfaces and define only the operations you need. Custom business methods compose handler primitives to implement domain logic. Keep your public API intentional and your handler interfaces generic.
