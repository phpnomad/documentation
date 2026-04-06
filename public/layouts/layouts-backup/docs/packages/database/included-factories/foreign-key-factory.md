# ForeignKeyFactory

The `ForeignKeyFactory` creates a standardized **foreign key column** that references another table's primary key. This factory provides consistent relationship column definitions across all entity tables.

## Basic Usage

```php
<?php

use PHPNomad\Database\Factories\Columns\ForeignKeyFactory;
use PHPNomad\Database\Factories\Columns\PrimaryKeyFactory;

class PostsTable extends Table
{
    public function getColumns(): array
    {
        return [
            (new PrimaryKeyFactory())->toColumn(),
            new Column('title', 'VARCHAR', [255], 'NOT NULL'),
            new Column('content', 'TEXT', null, 'NOT NULL'),
            (new ForeignKeyFactory('author'))->toColumn(),
        ];
    }
}
```

## Generated Column Definition

**Constructor:** `new ForeignKeyFactory('author')`

**Column name:** `author_id`
**Column type:** `BIGINT`
**Properties:** `NOT NULL`

**Generated SQL:**
```sql
CREATE TABLE wp_posts (
    id BIGINT AUTO_INCREMENT NOT NULL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    author_id BIGINT NOT NULL
);
```

## Naming Convention

The factory automatically appends `_id` to your entity name:

```php
// Input: 'author' → Output: 'author_id'
(new ForeignKeyFactory('author'))->toColumn()

// Input: 'category' → Output: 'category_id'
(new ForeignKeyFactory('category'))->toColumn()

// Input: 'parent_post' → Output: 'parent_post_id'
(new ForeignKeyFactory('parent_post'))->toColumn()
```

## Why Use This Factory?

**Consistency across relationships:**
```php
// ✅ GOOD: all foreign keys follow same pattern
class PostsTable extends Table
{
    public function getColumns(): array
    {
        return [
            (new PrimaryKeyFactory())->toColumn(),
            new Column('title', 'VARCHAR', [255], 'NOT NULL'),
            (new ForeignKeyFactory('author'))->toColumn(),
            (new ForeignKeyFactory('category'))->toColumn(),
        ];
    }
}

class CommentsTable extends Table
{
    public function getColumns(): array
    {
        return [
            (new PrimaryKeyFactory())->toColumn(),
            new Column('content', 'TEXT', null, 'NOT NULL'),
            (new ForeignKeyFactory('post'))->toColumn(),
            (new ForeignKeyFactory('author'))->toColumn(),
        ];
    }
}

// ❌ BAD: inconsistent foreign key definitions
class PostsTable extends Table
{
    public function getColumns(): array
    {
        return [
            new Column('author', 'INT', null, 'NOT NULL'),  // Wrong type
            new Column('categoryId', 'BIGINT', null, 'NOT NULL'),  // Wrong naming
        ];
    }
}

class CommentsTable extends Table
{
    public function getColumns(): array
    {
        return [
            new Column('post_id', 'VARCHAR', [50], 'NULL'),  // Wrong type, nullable
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
use PHPNomad\Database\Factories\Columns\ForeignKeyFactory;
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
            new Column('slug', 'VARCHAR', [255], 'NOT NULL'),
            new Column('content', 'TEXT', null, 'NOT NULL'),
            new Column('status', 'VARCHAR', [20], 'NOT NULL'),

            // Foreign keys
            (new ForeignKeyFactory('author'))->toColumn(),
            (new ForeignKeyFactory('category'))->toColumn(),

            // Timestamps
            (new DateCreatedFactory())->toColumn(),
            (new DateModifiedFactory())->toColumn(),
        ];
    }

    public function getIndices(): array
    {
        return [
            // Index foreign keys for join performance
            new Index(['author_id'], 'author_idx', 'INDEX'),
            new Index(['category_id'], 'category_idx', 'INDEX'),
            new Index(['slug'], 'slug_unique', 'UNIQUE'),
        ];
    }
}
```

## Self-Referential Foreign Keys

Use descriptive names for self-referential relationships:

```php
class PostsTable extends Table
{
    public function getColumns(): array
    {
        return [
            (new PrimaryKeyFactory())->toColumn(),
            new Column('title', 'VARCHAR', [255], 'NOT NULL'),

            // Self-referential foreign key
            (new ForeignKeyFactory('parent_post'))->toColumn(),
            // Creates column: parent_post_id
        ];
    }

    public function getIndices(): array
    {
        return [
            new Index(['parent_post_id'], 'parent_post_idx', 'INDEX'),
        ];
    }
}
```

## Nullable Foreign Keys

For optional relationships, make the column nullable:

```php
class PostsTable extends Table
{
    public function getColumns(): array
    {
        return [
            (new PrimaryKeyFactory())->toColumn(),
            new Column('title', 'VARCHAR', [255], 'NOT NULL'),

            // Required relationship
            (new ForeignKeyFactory('author'))->toColumn(),

            // Optional relationship - override constraint
            new Column('featured_image_id', 'BIGINT', null, 'NULL'),
        ];
    }
}
```

## Junction Table Usage

Foreign key factories work perfectly in junction tables:

```php
class PostTagsTable extends Table
{
    public function getColumns(): array
    {
        return [
            (new ForeignKeyFactory('post'))->toColumn(),
            (new ForeignKeyFactory('tag'))->toColumn(),
        ];
    }

    public function getIndices(): array
    {
        return [
            // Compound primary key
            new Index(['post_id', 'tag_id'], 'primary', 'PRIMARY KEY'),

            // Individual indexes for joins
            new Index(['post_id'], 'post_idx', 'INDEX'),
            new Index(['tag_id'], 'tag_idx', 'INDEX'),
        ];
    }
}
```

## Querying with Foreign Keys

```php
// Find posts by author
$authorPosts = $handler->get(['author_id' => 123]);

// Count posts per category
$categoryCount = $handler->count(['category_id' => 5]);

// Find posts using query builder
$posts = $handler
    ->where()
    ->equals('author_id', 123)
    ->equals('status', 'published')
    ->orderBy('date_created', 'DESC')
    ->getResults();
```

## What's Next

* [JunctionTable Class](/packages/database/tables/junction-table-class) — many-to-many relationships
* [Table Class](/packages/database/tables/table-class) — complete table definition reference
* [PrimaryKeyFactory](/packages/database/included-factories/primary-key-factory) — standardized primary key column
