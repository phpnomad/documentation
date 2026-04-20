# DateCreatedFactory

The `DateCreatedFactory` creates a standardized **timestamp column** that stores when a record was created. This factory provides consistent creation timestamp tracking across all entity tables.

## Basic Usage

```php
<?php

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
        ];
    }
}
```

## Generated Column Definition

The factory creates:

**Column name:** `date_created`
**Column type:** `DATETIME`
**Properties:** `NOT NULL DEFAULT CURRENT_TIMESTAMP`

**Generated SQL:**
```sql
CREATE TABLE wp_posts (
    id BIGINT AUTO_INCREMENT NOT NULL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    date_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

## Automatic Timestamp Behavior

When you insert a new record, the database automatically sets `date_created`:

```php
// Create and save new post
$newPost = new Post(null, 'My Title', 'Content', 123, null);
$savedPost = $handler->save($newPost);

// Database automatically set date_created
echo $savedPost->dateCreated->format('Y-m-d H:i:s');
// Output: 2024-01-15 14:23:45
```

**Generated INSERT:**
```sql
INSERT INTO wp_posts (title, content, author_id)
VALUES ('My Title', 'Content', 123);
-- date_created is automatically set to current timestamp
```

## Why Use This Factory?

**Consistency and auditability:**
```php
// ✅ GOOD: all tables track creation time the same way
class PostsTable extends Table
{
    public function getColumns(): array
    {
        return [
            (new PrimaryKeyFactory())->toColumn(),
            new Column('title', 'VARCHAR', [255], 'NOT NULL'),
            (new DateCreatedFactory())->toColumn(),
        ];
    }
}

class UsersTable extends Table
{
    public function getColumns(): array
    {
        return [
            (new PrimaryKeyFactory())->toColumn(),
            new Column('username', 'VARCHAR', [100], 'NOT NULL'),
            (new DateCreatedFactory())->toColumn(),
        ];
    }
}

// ❌ BAD: inconsistent timestamp columns
class PostsTable extends Table
{
    public function getColumns(): array
    {
        return [
            new Column('created_at', 'TIMESTAMP', null, 'NOT NULL'),
        ];
    }
}

class UsersTable extends Table
{
    public function getColumns(): array
    {
        return [
            new Column('creation_date', 'DATETIME', null, 'NULL'),
            // Different name, nullable!
        ];
    }
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

            // Timestamp columns at the end
            (new DateCreatedFactory())->toColumn(),
            (new DateModifiedFactory())->toColumn(),
        ];
    }
}
```

## Querying by Creation Date

```php
// Find posts created in the last 7 days
$recentPosts = $handler
    ->where()
    ->greaterThan('date_created', (new DateTime('-7 days'))->format('Y-m-d H:i:s'))
    ->orderBy('date_created', 'DESC')
    ->getResults();

// Count posts created today
$todayCount = $handler->count([
    'date_created >=' => (new DateTime('today'))->format('Y-m-d H:i:s')
]);
```

## What's Next

* [DateModifiedFactory](/packages/database/included-factories/date-modified-factory) — automatic timestamp on record updates
* [PrimaryKeyFactory](/packages/database/included-factories/primary-key-factory) — standardized primary key column
* [Table Class](/packages/database/tables/table-class) — complete table definition reference
