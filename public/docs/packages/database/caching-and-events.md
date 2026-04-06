# Caching and Events

PHPNomad's database handlers include built-in support for **automatic caching** and **event broadcasting**. These features are provided by the `CacheableService` and `EventStrategy` components, which are injected into handlers via the `DatabaseServiceProvider`.

Caching improves performance by storing frequently accessed data, while events enable reactive patterns where other parts of your system can respond to data changes without tight coupling.

## Overview

When a handler extends `IdentifiableDatabaseDatastoreHandler`, it automatically gets:

* **Caching** — Query results are cached based on configurable policies
* **Cache invalidation** — Mutations (save, delete) automatically invalidate affected cache entries
* **Event broadcasting** — Mutations trigger events that other services can listen to

This happens transparently—you don't need to write caching or event code in your handlers.

---

## Caching Strategy

### How Caching Works

The `CacheableService` wraps query operations with cache checks:

1. **Cache hit** — If data exists in cache and policy allows, return cached data
2. **Cache miss** — Execute the query, store result in cache, return data
3. **Invalidation** — Mutations (save, delete) clear relevant cache entries

### CacheableService API

```php
class CacheableService
{
    /**
     * Get data with caching
     *
     * @param string $operation - Operation name (e.g., 'find', 'get')
     * @param array $context - Context data (e.g., ['id' => 123])
     * @param callable $callback - Function to execute on cache miss
     */
    public function getWithCache(string $operation, array $context, callable $callback);

    /**
     * Get cached data directly (throws if not found)
     */
    public function get(array $context);

    /**
     * Clear cache for specific context
     */
    public function forget(array $context): void;

    /**
     * Clear all cache entries matching a pattern
     */
    public function forgetMatching(string $pattern): void;
}
```

### Example: Handler with Caching

```php
<?php

use PHPNomad\Cache\Services\CacheableService;
use PHPNomad\Database\Abstracts\IdentifiableDatabaseDatastoreHandler;

class PostHandler extends IdentifiableDatabaseDatastoreHandler
{
    private CacheableService $cache;

    public function __construct(
        DatabaseServiceProvider $serviceProvider,
        PostsTable $table,
        PostAdapter $adapter
    ) {
        $this->cache = $serviceProvider->cacheableService;
        $this->table = $table;
        $this->adapter = $adapter;
    }

    public function find(int $id): Post
    {
        return $this->cache->getWithCache(
            operation: 'find',
            context: ['id' => $id],
            callback: function() use ($id) {
                // This only runs on cache miss
                $row = $this->executeQuery("SELECT * FROM {$this->table->getTableName()} WHERE id = {$id}");
                return $this->adapter->toModel($row);
            }
        );
    }
}
```

**On first call:**
1. Cache miss → executes query
2. Stores result in cache
3. Returns post

**On subsequent calls:**
1. Cache hit → returns cached post
2. Query is never executed

---

### Cache Invalidation

When you save or delete a record, the handler automatically invalidates relevant cache entries:

```php
public function save(Model $item): Model
{
    $result = parent::save($item);
    
    // Automatically clears cache for this record
    $this->cache->forget(['id' => $item->getId()]);
    
    // Also clears list caches that might include this record
    $this->cache->forgetMatching('posts:list:*');
    
    return $result;
}

public function delete(Model $item): void
{
    parent::delete($item);
    
    // Automatically clears cache for this record
    $this->cache->forget(['id' => $item->getId()]);
    $this->cache->forgetMatching('posts:list:*');
}
```

**Note:** `IdentifiableDatabaseDatastoreHandler` handles this automatically. You only need custom invalidation for complex cache patterns.

---

### Cache Policies

Cache behavior is controlled by a `CachePolicy`:

```php
interface CachePolicy
{
    /**
     * Determine if this operation should use cache
     */
    public function shouldCache(string $operation, array $context): bool;

    /**
     * Generate cache key from context
     */
    public function getCacheKey(array $context): string;

    /**
     * Get cache TTL (time-to-live) in seconds
     */
    public function getTtl(array $context): int;
}
```

### Example: Custom Cache Policy

```php
<?php

use PHPNomad\Cache\Interfaces\CachePolicy;

class PostCachePolicy implements CachePolicy
{
    public function shouldCache(string $operation, array $context): bool
    {
        // Cache reads, not writes
        return in_array($operation, ['find', 'get', 'where']);
    }

    public function getCacheKey(array $context): string
    {
        // Generate unique key from context
        return 'posts:' . md5(serialize($context));
    }

    public function getTtl(array $context): int
    {
        // Cache for 1 hour
        return 3600;
    }
}
```

---

### Cache Key Patterns

Good cache key design prevents collisions and enables targeted invalidation:

**Single record:**
```php
$key = "posts:{$id}";
// posts:123
```

**List with filters:**
```php
$key = "posts:list:" . md5(serialize(['author_id' => 123, 'status' => 'published']));
// posts:list:a3f2e1d...
```

**Count queries:**
```php
$key = "posts:count:" . md5(serialize(['status' => 'published']));
// posts:count:b4c3d2e...
```

**Wildcard invalidation:**
```php
// Clear all list caches when any post changes
$this->cache->forgetMatching('posts:list:*');

// Clear all post caches (lists and single records)
$this->cache->forgetMatching('posts:*');
```

---

## Event Broadcasting

### How Events Work

Handlers broadcast events after mutations, allowing other parts of your system to react:

* **RecordCreated** — Fired after `save()` creates a new record
* **RecordUpdated** — Fired after `save()` updates an existing record
* **RecordDeleted** — Fired after `delete()` removes a record

Events are **asynchronous** by default—listeners don't block the handler.

---

### EventStrategy API

```php
interface EventStrategy
{
    /**
     * Broadcast an event to all registered listeners
     *
     * @param object $event The event object
     */
    public function broadcast(object $event): void;

    /**
     * Register a listener for an event type
     *
     * @param string $eventClass The event class name
     * @param callable $listener The listener callback
     */
    public function listen(string $eventClass, callable $listener): void;
}
```

---

### Example: Handler with Events

```php
<?php

use PHPNomad\Events\Interfaces\EventStrategy;
use PHPNomad\Database\Events\RecordCreated;
use PHPNomad\Database\Events\RecordUpdated;
use PHPNomad\Database\Events\RecordDeleted;

class PostHandler extends IdentifiableDatabaseDatastoreHandler
{
    private EventStrategy $events;

    public function __construct(
        DatabaseServiceProvider $serviceProvider,
        PostsTable $table,
        PostAdapter $adapter
    ) {
        $this->events = $serviceProvider->eventStrategy;
        $this->table = $table;
        $this->adapter = $adapter;
    }

    public function save(Model $item): Model
    {
        $isNew = !$item->getId();
        
        $result = parent::save($item);
        
        // Broadcast appropriate event
        if ($isNew) {
            $this->events->broadcast(new RecordCreated('posts', $result));
        } else {
            $this->events->broadcast(new RecordUpdated('posts', $result));
        }
        
        return $result;
    }

    public function delete(Model $item): void
    {
        parent::delete($item);
        
        $this->events->broadcast(new RecordDeleted('posts', $item));
    }
}
```

**Note:** `IdentifiableDatabaseDatastoreHandler` broadcasts these events automatically.

---

### Listening to Events

Register listeners in your service provider:

```php
<?php

use PHPNomad\Database\Events\RecordCreated;
use PHPNomad\Database\Events\RecordUpdated;
use PHPNomad\Database\Events\RecordDeleted;

class PostServiceProvider
{
    public function __construct(
        private EventStrategy $events,
        private NotificationService $notifications
    ) {}

    public function boot(): void
    {
        // Listen for post creation
        $this->events->listen(RecordCreated::class, function(RecordCreated $event) {
            if ($event->table === 'posts') {
                $post = $event->model;
                $this->notifications->sendNewPostNotification($post);
            }
        });

        // Listen for post updates
        $this->events->listen(RecordUpdated::class, function(RecordUpdated $event) {
            if ($event->table === 'posts') {
                $post = $event->model;
                $this->notifications->sendPostUpdatedNotification($post);
            }
        });

        // Listen for post deletion
        $this->events->listen(RecordDeleted::class, function(RecordDeleted $event) {
            if ($event->table === 'posts') {
                // Clean up related data
                $this->cleanupPostRelations($event->model->getId());
            }
        });
    }
}
```

---

### Custom Events

You can broadcast domain-specific events:

```php
<?php

namespace App\Events;

class PostPublished
{
    public function __construct(
        public readonly Post $post,
        public readonly DateTime $publishedAt
    ) {}
}
```

**Broadcast it:**
```php
class PostService
{
    public function __construct(
        private PostDatastore $posts,
        private EventStrategy $events
    ) {}

    public function publish(int $postId): void
    {
        $post = $this->posts->find($postId);
        
        $published = new Post(
            id: $post->id,
            title: $post->title,
            content: $post->content,
            authorId: $post->authorId,
            publishedDate: new DateTime()
        );
        
        $this->posts->save($published);
        
        // Broadcast custom event
        $this->events->broadcast(new PostPublished($published, new DateTime()));
    }
}
```

**Listen for it:**
```php
$this->events->listen(PostPublished::class, function(PostPublished $event) {
    $this->emailService->notifySubscribers($event->post);
    $this->searchIndex->updatePost($event->post);
});
```

---

## Combining Caching and Events

Caching and events work together seamlessly:

```php
class PostHandler extends IdentifiableDatabaseDatastoreHandler
{
    public function save(Model $item): Model
    {
        $isNew = !$item->getId();
        
        // Save to database
        $result = parent::save($item);
        
        // Clear cache
        $this->cache->forget(['id' => $result->getId()]);
        $this->cache->forgetMatching('posts:list:*');
        
        // Broadcast event
        if ($isNew) {
            $this->events->broadcast(new RecordCreated('posts', $result));
        } else {
            $this->events->broadcast(new RecordUpdated('posts', $result));
        }
        
        return $result;
    }
}
```

**Flow:**
1. **Write** — Save to database
2. **Invalidate** — Clear affected caches
3. **Notify** — Broadcast event to listeners
4. **React** — Listeners update derived data, send notifications, etc.

---

## Event-Driven Cache Warming

Use events to proactively warm caches:

```php
$this->events->listen(RecordUpdated::class, function(RecordUpdated $event) {
    if ($event->table === 'posts') {
        // Warm cache for commonly accessed queries
        $this->postDatastore->get(['status' => 'published']);
        $this->postDatastore->get(['featured' => true]);
    }
});
```

---

## Cache Miss Events

`CacheableService` broadcasts a `CacheMissed` event you can track:

```php
use PHPNomad\Cache\Events\CacheMissed;

$this->events->listen(CacheMissed::class, function(CacheMissed $event) {
    // Log cache misses for monitoring
    $this->logger->info("Cache miss: {$event->operation}", $event->context);
});
```

---

## Best Practices

### Cache Strategically

```php
// ✅ GOOD: cache expensive queries
$posts = $this->cache->getWithCache('list', ['author_id' => 123], fn() => 
    $this->queryBuilder->select('*')->from($this->table)->where(...)->build()
);

// ❌ BAD: caching single writes
$this->cache->getWithCache('save', [], fn() => $this->save($post));
```

### Use Descriptive Cache Keys

```php
// ✅ GOOD: clear, structured keys
"posts:123"
"posts:list:author:456"
"posts:count:published"

// ❌ BAD: opaque keys
"p123"
"query_result"
```

### Invalidate Broadly on Writes

```php
// ✅ GOOD: clear related caches
$this->cache->forget(['id' => $id]);
$this->cache->forgetMatching('posts:list:*');
$this->cache->forgetMatching('posts:count:*');

// ❌ BAD: only clear one entry
$this->cache->forget(['id' => $id]);
```

### Keep Events Lightweight

```php
// ✅ GOOD: quick event listener
$this->events->listen(RecordCreated::class, fn($e) => 
    $this->queue->push(new SendNotificationJob($e->model))
);

// ❌ BAD: slow event listener blocks handler
$this->events->listen(RecordCreated::class, function($e) {
    $this->emailService->sendToAllSubscribers($e->model);  // Slow!
});
```

### Use Events for Side Effects

```php
// ✅ GOOD: side effects in event listeners
$this->events->listen(PostPublished::class, fn($e) => 
    $this->searchIndex->update($e->post)
);

// ❌ BAD: side effects in handler
public function save(Model $item): Model {
    $result = parent::save($item);
    $this->searchIndex->update($result);  // Couples handler to search
    return $result;
}
```

---

## Related Documentation

* [Event Package](/packages/event/introduction) — Core event interfaces (`Event`, `EventStrategy`, `CanHandle`)
* [Event Listeners](/core-concepts/bootstrapping/initializers/event-listeners) — Setting up event listeners in initializers
* [Event Bindings](/core-concepts/bootstrapping/initializers/event-binding) — Binding platform events to application events

## What's Next

* [Database Handlers](/packages/database/handlers/introduction) — handlers that use caching and events
* [Query Building](/packages/database/query-building) — building cacheable queries
* [Database Service Provider](/packages/database/database-service-provider) — configuring caching and events
