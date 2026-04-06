# PrimaryKeyFactory

The `PrimaryKeyFactory` creates a standardized **auto-incrementing primary key column** named `id`. This factory provides a consistent primary key definition across all entity tables.

## Basic Usage

```php
<?php

use PHPNomad\Database\Factories\Columns\PrimaryKeyFactory;

class PostsTable extends Table
{
    public function getColumns(): array
    {
        return [
            (new PrimaryKeyFactory())->toColumn(),
            new Column('title', 'VARCHAR', [255], 'NOT NULL'),
            new Column('content', 'TEXT', null, 'NOT NULL'),
        ];
    }
}
```

## Generated Column Definition

The factory creates:

**Column name:** `id`
**Column type:** `BIGINT`
**Properties:** `AUTO_INCREMENT NOT NULL PRIMARY KEY`

**Generated SQL:**
```sql
CREATE TABLE wp_posts (
    id BIGINT AUTO_INCREMENT NOT NULL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL
);
```

## Why Use This Factory?

**Consistency across tables:**
```php
// ✅ GOOD: all tables have same primary key definition
class PostsTable extends Table
{
    public function getColumns(): array
    {
        return [
            (new PrimaryKeyFactory())->toColumn(),
            // ...
        ];
    }
}

class UsersTable extends Table
{
    public function getColumns(): array
    {
        return [
            (new PrimaryKeyFactory())->toColumn(),
            // ...
        ];
    }
}

// ❌ BAD: manual definitions can vary
class PostsTable extends Table
{
    public function getColumns(): array
    {
        return [
            new Column('id', 'BIGINT', null, 'AUTO_INCREMENT NOT NULL PRIMARY KEY'),
            // ...
        ];
    }
}

class UsersTable extends Table
{
    public function getColumns(): array
    {
        return [
            new Column('user_id', 'INT', null, 'AUTO_INCREMENT NOT NULL PRIMARY KEY'),
            // Different name and type!
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
use PHPNomad\Database\Factories\Index;
use PHPNomad\Database\Factories\Columns\PrimaryKeyFactory;

class PostsTable extends Table
{
    public function getColumns(): array
    {
        return [
            // Primary key always first
            (new PrimaryKeyFactory())->toColumn(),

            // Business columns
            new Column('title', 'VARCHAR', [255], 'NOT NULL'),
            new Column('slug', 'VARCHAR', [255], 'NOT NULL'),
            new Column('content', 'TEXT', null, 'NOT NULL'),
            new Column('author_id', 'BIGINT', null, 'NOT NULL'),
        ];
    }

    public function getIndices(): array
    {
        return [
            // No need to define primary key index - it's automatic
            new Index(['slug'], 'slug_unique', 'UNIQUE'),
            new Index(['author_id'], 'author_idx', 'INDEX'),
        ];
    }
}
```

## What's Next

* [DateCreatedFactory](/packages/database/included-factories/date-created-factory) — automatic timestamp on record creation
* [DateModifiedFactory](/packages/database/included-factories/date-modified-factory) — automatic timestamp on record updates
* [Table Class](/packages/database/tables/table-class) — complete table definition reference
