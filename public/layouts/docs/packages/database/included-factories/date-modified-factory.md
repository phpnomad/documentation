# DateModifiedFactory

The `DateModifiedFactory` creates a standardized **timestamp column** that automatically updates whenever a record is modified. This factory provides consistent update timestamp tracking across all entity tables.

## Basic Usage

```php
<?php

use PHPNomad\Database\Factories\Columns\DateModifiedFactory;
use PHPNomad\Database\Factories\Columns\DateCreatedFactory;
use PHPNomad\Database\Factories\Columns\PrimaryKeyFactory;

class PostsTable extends Table
{
    public function getColumns(): array
    {
        return [
            (new PrimaryKeyFactory())->toColumn(),
            new Column('title', 'VARCHAR', [255], 'NOT NULL'),
            new Column('content', 'TEXT', null, 'NOT NULL'),
            (new DateCreatedFactory())->toColumn(),
            (new DateModifiedFactory())->toColumn(),
        ];
    }
}
```

## Generated Column Definition

The factory creates:

**Column name:** `date_modified`
**Column type:** `DATETIME`
**Properties:** `NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`

**Generated SQL:**
```sql
CREATE TABLE wp_posts (
    id BIGINT AUTO_INCREMENT NOT NULL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    date_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_modified DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## Automatic Timestamp Behavior

The `date_modified` column automatically updates on every UPDATE:

```php
// Create new post
$newPost = new Post(null, 'Title', 'Content', 123, null);
$savedPost = $handler->save($newPost);

// Initial timestamps are the same
echo $savedPost->dateCreated->format('Y-m-d H:i:s');
// Output: 2024-01-15 14:23:45
echo $savedPost->dateModified->format('Y-m-d H:i:s');
// Output: 2024-01-15 14:23:45

// Update the post later
sleep(5);
$savedPost->title = 'New Title';
$updatedPost = $handler->save($savedPost);

// date_created unchanged, date_modified updated
echo $updatedPost->dateCreated->format('Y-m-d H:i:s');
// Output: 2024-01-15 14:23:45 (unchanged)
echo $updatedPost->dateModified->format('Y-m-d H:i:s');
// Output: 2024-01-15 14:23:50 (updated)
```

**Generated UPDATE:**
```sql
UPDATE wp_posts
SET title = 'New Title', content = 'Content'
WHERE id = 123;
-- date_modified is automatically set to current timestamp
```

## Why Use This Factory?

**Automatic change tracking:**
```php
// ✅ GOOD: all tables track modifications the same way
class PostsTable extends Table
{
    public function getColumns(): array
    {
        return [
            (new PrimaryKeyFactory())->toColumn(),
            new Column('title', 'VARCHAR', [255], 'NOT NULL'),
            (new DateCreatedFactory())->toColumn(),
            (new DateModifiedFactory())->toColumn(),
        ];
    }
}

// ❌ BAD: manual timestamp management
class PostsTable extends Table
{
    public function getColumns(): array
    {
        return [
            new Column('updated_at', 'DATETIME', null, 'NULL'),
            // Not automatic - requires manual updates in code
        ];
    }
}

// ❌ BAD: application code managing timestamps
public function save(Model $item): Model
{
    $item->dateModified = new DateTime();  // Manual!
    return parent::save($item);
}
```

## Common Usage Pattern

```php
<?php

namespace App\Service\Datastores\Post;

use PHPNomad\Database\Abstracts\Table;
use PHPNomad\Database\Factories\Column;
use PHPNomad\Database\Factories\Columns\PrimaryKeyFactory;
use PHPNomad\Database\Factories\Columns\DateCreatedFactory;
use PHPNomad\Database\Factories\Columns\DateModifiedFactory;

class PostsTable extends Table
{
    public function getColumns(): array
    {
        return [
            (new PrimaryKeyFactory())->toColumn(),

            // Business columns
            new Column('title', 'VARCHAR', [255], 'NOT NULL'),
            new Column('content', 'TEXT', null, 'NOT NULL'),
            new Column('author_id', 'BIGINT', null, 'NOT NULL'),
            new Column('status', 'VARCHAR', [20], 'NOT NULL'),

            // Timestamp columns at the end
            (new DateCreatedFactory())->toColumn(),
            (new DateModifiedFactory())->toColumn(),
        ];
    }
}
```

## Querying by Modification Date

```php
// Find posts modified in the last hour
$recentlyModified = $handler
    ->where()
    ->greaterThan('date_modified', (new DateTime('-1 hour'))->format('Y-m-d H:i:s'))
    ->orderBy('date_modified', 'DESC')
    ->getResults();

// Find stale posts (not modified in 90 days)
$stalePosts = $handler
    ->where()
    ->lessThan('date_modified', (new DateTime('-90 days'))->format('Y-m-d H:i:s'))
    ->getResults();

// Check if post was modified after creation
foreach ($posts as $post) {
    if ($post->dateModified > $post->dateCreated) {
        echo "Post {$post->id} has been edited\n";
    }
}
```

## Change Detection

Use `date_modified` to detect and react to changes:

```php
// Cache invalidation based on modification time
public function getCachedPost(int $id): Post
{
    $cacheKey = "post:{$id}";
    $cached = $this->cache->get($cacheKey);

    if ($cached) {
        $current = $this->handler->find($id);

        // Invalidate if modified since cache
        if ($current->dateModified > $cached->dateModified) {
            $this->cache->forget($cacheKey);
            $this->cache->set($cacheKey, $current);
            return $current;
        }

        return $cached;
    }

    $post = $this->handler->find($id);
    $this->cache->set($cacheKey, $post);
    return $post;
}
```

## What's Next

* [DateCreatedFactory](/packages/database/included-factories/date-created-factory) — automatic timestamp on record creation
* [PrimaryKeyFactory](/packages/database/included-factories/primary-key-factory) — standardized primary key column
* [Table Class](/packages/database/tables/table-class) — complete table definition reference
