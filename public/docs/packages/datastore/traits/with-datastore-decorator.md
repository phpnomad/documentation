# WithDatastoreDecorator Trait

The `WithDatastoreDecorator` trait provides automatic implementations of the base [`Datastore`](/packages/datastore/interfaces/datastore) interface methods by delegating to a `$handler` property. It eliminates boilerplate code when your Core datastore implementation is a pure pass-through to a handler.

## What It Provides

This trait implements three methods:

* `get(array $args = []): iterable`
* `save(Model $item): Model`
* `delete(Model $item): void`

Each method simply forwards the call to `$this->handler` with the same parameters.

## Requirements

To use this trait, your class must:

1. **Implement `Datastore`** — the trait provides the method bodies.
2. **Have a `$handler` property** — must be of type `DatastoreHandler`.
3. **Initialize the handler** — typically via constructor injection.

## Basic Usage

```php
<?php

use PHPNomad\Datastore\Interfaces\Datastore;
use PHPNomad\Datastore\Interfaces\DatastoreHandler;
use PHPNomad\Datastore\Traits\WithDatastoreDecorator;

final class PostDatastore implements Datastore
{
    use WithDatastoreDecorator;

    public function __construct(
        private DatastoreHandler $handler
    ) {}
}
```

That's it. The trait provides `get()`, `save()`, and `delete()` automatically.

## Generated Code

The trait generates code equivalent to:

```php
final class PostDatastore implements Datastore
{
    private DatastoreHandler $handler;

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
}
```

By using the trait, you avoid writing this repetitive delegation code.

## When to Use This Trait

Use `WithDatastoreDecorator` when:

* Your Core datastore doesn't add logic—it just passes calls to the handler.
* You want to reduce boilerplate in simple implementations.
* You're building a standard database-backed datastore.

## When NOT to Use This Trait

Don't use `WithDatastoreDecorator` if you need to:

* Add logging, caching, or validation before delegating.
* Transform data between the public API and handler.
* Implement custom behavior in `get()`, `save()`, or `delete()`.

In these cases, implement the methods manually.

## Example: Custom Logic in `save()`

If you need custom behavior in one method, implement it manually and use the trait for the others:

```php
final class PostDatastore implements Datastore
{
    use WithDatastoreDecorator {
        save as private traitSave; // Rename trait's save method
    }

    public function __construct(
        private DatastoreHandler $handler,
        private LoggerStrategy $logger
    ) {}

    // Custom save with logging
    public function save(Model $item): Model
    {
        $this->logger->info("Saving post: {$item->getId()}");
        return $this->traitSave($item); // Delegate to trait
    }

    // get() and delete() provided by trait
}
```

Alternatively, just implement `save()` yourself and let the trait handle `get()` and `delete()`:

```php
final class PostDatastore implements Datastore
{
    use WithDatastoreDecorator;

    public function __construct(
        private DatastoreHandler $handler,
        private LoggerStrategy $logger
    ) {}

    // Custom save with logging
    public function save(Model $item): Model
    {
        $this->logger->info("Saving post: {$item->getId()}");
        return $this->handler->save($item);
    }

    // get() and delete() provided by trait
}
```

## Combining with Other Decorator Traits

You can use multiple decorator traits together when your interface extends multiple capabilities:

```php
interface PostDatastore extends 
    Datastore,
    DatastoreHasPrimaryKey,
    DatastoreHasCounts
{
    // get(), save(), delete(), find(), count()
}

final class PostDatastoreImpl implements PostDatastore
{
    use WithDatastoreDecorator;        // get(), save(), delete()
    use WithDatastorePrimaryKeyDecorator {
        // Resolve conflict: both traits provide get(), save(), delete()
        WithDatastorePrimaryKeyDecorator::get insteadof WithDatastoreDecorator;
        WithDatastorePrimaryKeyDecorator::save insteadof WithDatastoreDecorator;
        WithDatastorePrimaryKeyDecorator::delete insteadof WithDatastoreDecorator;
    }
    use WithDatastoreCountDecorator;   // count()

    public function __construct(
        private DatastoreHandlerHasPrimaryKey & DatastoreHandlerHasCounts $handler
    ) {}
}
```

**Note:** In practice, you'd typically use **only** `WithDatastorePrimaryKeyDecorator` since it extends `WithDatastoreDecorator` and includes all base methods. The example above shows how to resolve conflicts if needed.

## Handler Type Requirements

The `$handler` property must implement `DatastoreHandler`:

```php
interface DatastoreHandler
{
    public function get(array $args = []): iterable;
    public function save(Model $item): Model;
    public function delete(Model $item): void;
}
```

Most handlers extend this interface with additional capabilities (e.g., `DatastoreHandlerHasPrimaryKey`), which is fine—the trait only calls the base methods.

## Best Practices

* **Use traits for pure delegation** — if you're adding logic, implement manually.
* **Name the handler `$handler`** — the trait expects this property name.
* **Inject via constructor** — don't create handlers inside the datastore.
* **Combine with extension traits** — use `WithDatastorePrimaryKeyDecorator` for extended interfaces.

## What's Next

* [Datastore Interface](/packages/datastore/interfaces/datastore) — the interface this trait implements
* [WithDatastorePrimaryKeyDecorator](/packages/datastore/traits/with-datastore-primary-key-decorator) — adds `find()` method
* [Core Implementation](/packages/datastore/core-implementation) — when to use traits vs manual implementation
* [Logger Package](/packages/logger/introduction) — LoggerStrategy interface used in examples above
