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

When you insert a new record, the database automatically sets `dateCreated`:

```php
// Create and save new post
$savedPost = $handler->create([
    'title' => 'My Title',
    'content' => 'Content',
    'authorId' => 123,
]);

// Database automatically set dateCreated
echo $savedPost->dateCreated;
// Output: 2024-01-15 14:23:45
```

**Generated INSERT:**
```sql
INSERT INTO wp_posts (title, content, authorId, dateCreated)
VALUES ('My Title', 'Content', 123, '2024-01-15 14:23:45');
-- The framework supplies dateCreated via a PHP-side default; the DB-side
-- DEFAULT CURRENT_TIMESTAMP remains as a backstop for inserts that bypass
-- the framework.
```

## PHP-Side Default (since 2.2.0)

As of `phpnomad/db` 2.2.0, the factory configures the column with a `phpDefault` callable as well as the DB-side `DEFAULT CURRENT_TIMESTAMP` clause. The callable returns a current MySQL-format datetime string (`Y-m-d H:i:s`) and is invoked by `WithDatastoreHandlerMethods::create()` for any insert where the caller did not supply `dateCreated` explicitly.

Two consequences:

1. The value that lands in the row also lands in the in-memory `DataModel` returned from `create()` — without re-reading the row back from the database. This is what makes `create()` safe behind read/write-split routers (ProxySQL, MaxScale, RDS Proxy, Aurora) that previously had a small chance of routing the post-insert `SELECT` to a replica that hadn't yet replicated the write.
2. Callers that do supply `dateCreated` win — the PHP default only fills in for absent columns. Backfills, imports, and tests that need to assert specific timestamps continue to work.

The DB-side `DEFAULT CURRENT_TIMESTAMP` clause is retained so rows inserted outside the framework (raw SQL, other applications, replication tooling) still get a sensible value.

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
