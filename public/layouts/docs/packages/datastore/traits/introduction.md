# Decorator Traits

Decorator traits in PHPNomad are **code generators** that eliminate boilerplate when building datastore implementations. They automatically delegate method calls from your datastore class to the underlying handler, so you don't have to write repetitive pass-through methods by hand.

When you implement a datastore interface that extends something like `DatastoreHasPrimaryKey`, you need to provide implementations for `get()`, `save()`, `delete()`, *and* `find()`. If your class is just delegating all those calls to a handler, that's a lot of mechanical code. Decorator traits collapse that down to a single `use` statement.

## Why Decorator Traits Exist

In the two-level datastore architecture, your **Core implementation** sits between the public `Datastore` interface and the `DatastoreHandler` that talks to storage. Most of the time, your Core class doesn't add logic—it just forwards calls:

```php
final class PostDatastore implements DatastoreHasPrimaryKey
{
    public function __construct(private DatastoreHandlerHasPrimaryKey $handler) {}

    public function get(array $args = []): iterable
    {
        return $this->handler->get($args);
    }

    public function save(Model $item): Model
    {
        return $this->handler->save($item);
    }

    public function delete(Model $item): void
    {
        $this->handler->delete($item);
    }

    public function find(int $id): Model
    {
        return $this->handler->find($id);
    }
}
```

Every method is identical: `return $this->handler->methodName(...)`. That's tedious to write and maintain. Decorator traits replace all of that with:

```php
final class PostDatastore implements DatastoreHasPrimaryKey
{
    use WithDatastorePrimaryKeyDecorator;

    public function __construct(private DatastoreHandlerHasPrimaryKey $handler) {}
}
```

**That's it.** The trait provides all four methods automatically.

## How They Work

Each decorator trait corresponds to one of the [datastore interfaces](/packages/datastore/interfaces/introduction). The trait provides method implementations that delegate to a `$handler` property.

When you `use` the trait, you must:
1. Store the handler in a property named `$handler`.
2. Ensure the handler implements the matching handler interface.

The trait will generate the delegation code for every method in that interface.

## Available Decorator Traits

PHPNomad provides four decorator traits, one for each standard interface:

### `WithDatastoreDecorator`

Decorates the base **`Datastore`** interface.

**Provides:**
- `get(array $args = []): iterable`
- `save(Model $item): Model`
- `delete(Model $item): void`

**Requires handler type:** `DatastoreHandler`

**Usage:**
```php
final class PostDatastore implements Datastore
{
    use WithDatastoreDecorator;

    public function __construct(private DatastoreHandler $handler) {}
}
```

---

### `WithDatastorePrimaryKeyDecorator`

Decorates **`DatastoreHasPrimaryKey`** (which extends `Datastore`).

**Provides:**
- All methods from `WithDatastoreDecorator`
- `find(int $id): Model`

**Requires handler type:** `DatastoreHandlerHasPrimaryKey`

**Usage:**
```php
final class PostDatastore implements DatastoreHasPrimaryKey
{
    use WithDatastorePrimaryKeyDecorator;

    public function __construct(private DatastoreHandlerHasPrimaryKey $handler) {}
}
```

---

### `WithDatastoreWhereDecorator`

Decorates **`DatastoreHasWhere`** (which extends `Datastore`).

**Provides:**
- All methods from `WithDatastoreDecorator`
- `where(): DatastoreWhereQuery`

**Requires handler type:** `DatastoreHandlerHasWhere`

**Usage:**
```php
final class PostDatastore implements DatastoreHasWhere
{
    use WithDatastoreWhereDecorator;

    public function __construct(private DatastoreHandlerHasWhere $handler) {}
}
```

---

### `WithDatastoreCountDecorator`

Decorates **`DatastoreHasCounts`** (which extends `Datastore`).

**Provides:**
- All methods from `WithDatastoreDecorator`
- `count(array $args = []): int`

**Requires handler type:** `DatastoreHandlerHasCounts`

**Usage:**
```php
final class PostDatastore implements DatastoreHasCounts
{
    use WithDatastoreCountDecorator;

    public function __construct(private DatastoreHandlerHasCounts $handler) {}
}
```

---

## Composing Multiple Traits

If your datastore interface extends multiple capabilities, you can use multiple traits together. PHP allows this as long as there are no method name conflicts (and PHPNomad's traits are designed to compose cleanly).

**Example: combining primary key and counting**

```php
interface PostDatastore extends DatastoreHasPrimaryKey, DatastoreHasCounts
{
    // find(), get(), save(), delete(), count()
}

final class PostDatastoreImpl implements PostDatastore
{
    use WithDatastorePrimaryKeyDecorator;
    use WithDatastoreCountDecorator;

    public function __construct(
        private DatastoreHandlerHasPrimaryKey & DatastoreHandlerHasCounts $handler
    ) {}
}
```

Both traits delegate to `$this->handler`, and the handler implements both interfaces.

## When NOT to Use Decorator Traits

Decorator traits are perfect for **pass-through implementations** where you don't need to add logic. But if you need to:

- Transform data before or after handler calls
- Add caching, logging, or authorization checks
- Override specific methods with custom behavior

Then you should **implement the methods manually** instead of using the trait.

**Example: custom logic in `find()`**

```php
final class PostDatastore implements DatastoreHasPrimaryKey
{
    use WithDatastoreDecorator; // Only for get/save/delete

    public function __construct(
        private DatastoreHandlerHasPrimaryKey $handler,
        private LoggerStrategy $logger
    ) {}

    // Custom implementation with logging
    public function find(int $id): Model
    {
        $this->logger->info("Fetching post {$id}");
        return $this->handler->find($id);
    }
}
```

Here we use `WithDatastoreDecorator` for the basic methods, but implement `find()` ourselves to add logging.

## Real-World Example: Full Composition

Here's a realistic example showing how traits simplify a datastore with multiple capabilities and one custom method:

```php
interface PostDatastore extends 
    DatastoreHasPrimaryKey, 
    DatastoreHasWhere, 
    DatastoreHasCounts
{
    public function findPublishedPosts(int $authorId): iterable;
}

final class PostDatastoreImpl implements PostDatastore
{
    use WithDatastorePrimaryKeyDecorator;
    use WithDatastoreWhereDecorator;
    use WithDatastoreCountDecorator;

    public function __construct(
        private DatastoreHandlerHasPrimaryKey & 
                DatastoreHandlerHasWhere & 
                DatastoreHandlerHasCounts $handler
    ) {}

    // Custom business method - not auto-generated
    public function findPublishedPosts(int $authorId): iterable
    {
        return $this->where()
            ->equals('authorId', $authorId)
            ->lessThanOrEqual('publishedDate', new DateTime())
            ->getResults();
    }
}
```

The traits provide `get()`, `save()`, `delete()`, `find()`, `where()`, and `count()` automatically. You only write the custom `findPublishedPosts()` method by hand.

## Best Practices

When working with decorator traits:

- **Use traits for delegation only** — if you need logic, implement methods manually.
- **Name the handler property `$handler`** — traits expect this name.
- **Match handler types to interfaces** — if you implement `DatastoreHasPrimaryKey`, use `DatastoreHandlerHasPrimaryKey`.
- **Compose traits freely** — multiple traits work together as long as interfaces align.
- **Override when needed** — you can always implement specific methods yourself instead of using the trait's version.

## What's Next

To understand how handlers work and what they're responsible for, see:

- [Core Implementation](/packages/datastore/core-implementation) — when to use traits vs manual implementation
- [Database Handlers](/packages/database/handlers/introduction) — the handler side of the delegation contract
- [Datastore Interfaces](/packages/datastore/interfaces/introduction) — the public contracts these traits implement
- [Logger Package](/packages/logger/introduction) — LoggerStrategy for logging in decorators
