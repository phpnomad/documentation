# Column and Index Factories

PHPNomad's database package provides **pre-built factories** for common column and index patterns. These factories eliminate repetitive schema definitions and ensure consistency across tables. Instead of manually defining columns with `Column` every time, you use specialized factories that encode best practices and standard patterns.

Factories are **building blocks** you compose in your [table definitions](/packages/database/tables/introduction). Each factory produces properly configured column or index objects with sensible defaults, while still allowing customization when needed.

## Why Factories Exist

Without factories, every table would repeat the same patterns:

```php
// Repetitive: defining a primary key in every table
$this->columnFactory->int('id', 11)->autoIncrement()->notNull();

// Repetitive: defining timestamps in every table
$this->columnFactory->datetime('created_at')->default('CURRENT_TIMESTAMP')->notNull();
$this->columnFactory->datetime('updated_at')
    ->default('CURRENT_TIMESTAMP')
    ->onUpdate('CURRENT_TIMESTAMP')
    ->notNull();
```

With factories, these become:

```php
$this->primaryKeyFactory->create('id');
$this->dateCreatedFactory->create('created_at');
$this->dateModifiedFactory->create('updated_at');
```

This reduces duplication, prevents typos, and makes table definitions scannable.

## Column Factories

PHPNomad provides four specialized column factories for the most common patterns.

### `PrimaryKeyFactory`

Creates **auto-increment integer primary keys**—the standard pattern for identifying rows.

**What it creates:**
* `INT(11)` column (or `BIGINT` if specified)
* `NOT NULL` constraint
* `AUTO_INCREMENT` attribute
* Primary key designation

**Usage:**

```php
final class PostTable implements Table
{
    public function __construct(
        private Column $columnFactory,
        private PrimaryKeyFactory $primaryKeyFactory
    ) {}

    public function getColumns(): array
    {
        return [
            $this->primaryKeyFactory->create('id'),
            // other columns...
        ];
    }

    public function getPrimaryKey(): PrimaryKey
    {
        return $this->primaryKeyFactory->create('id');
    }
}
```

**Customization:**

```php
// Use BIGINT for very large tables
$this->primaryKeyFactory->create('id', size: 'big');
```

---

### `DateCreatedFactory`

Creates **timestamp columns** that automatically capture when a row was created.

**What it creates:**
* `DATETIME` column
* `DEFAULT CURRENT_TIMESTAMP`
* `NOT NULL` constraint

**Usage:**

```php
public function getColumns(): array
{
    return [
        $this->primaryKeyFactory->create('id'),
        $this->columnFactory->string('title', 255)->notNull(),
        $this->dateCreatedFactory->create('created_at'),
    ];
}
```

This column is set once when the row is inserted and never changes.

**When to use:**
* Audit trails (knowing when records were added)
* Sorting by creation time
* Tracking data freshness

---

### `DateModifiedFactory`

Creates **timestamp columns** that automatically update whenever a row changes.

**What it creates:**
* `DATETIME` column
* `DEFAULT CURRENT_TIMESTAMP`
* `ON UPDATE CURRENT_TIMESTAMP`
* `NOT NULL` constraint

**Usage:**

```php
public function getColumns(): array
{
    return [
        $this->primaryKeyFactory->create('id'),
        $this->columnFactory->string('title', 255)->notNull(),
        $this->dateCreatedFactory->create('created_at'),
        $this->dateModifiedFactory->create('updated_at'),
    ];
}
```

This column is updated automatically by the database every time the row is modified.

**When to use:**
* Tracking when records were last changed
* Cache invalidation (e.g., "invalidate if `updated_at` is newer than cached timestamp")
* Detecting stale data

---

### `ForeignKeyFactory`

Creates **foreign key columns** that reference primary keys in other tables.

**What it creates:**
* `INT` column matching the referenced table's primary key type
* `NOT NULL` constraint (by default)
* Foreign key constraint pointing to the target table
* Optional `ON DELETE` and `ON UPDATE` rules

**Usage:**

```php
public function __construct(
    private Column $columnFactory,
    private ForeignKeyFactory $foreignKeyFactory
) {}

public function getColumns(): array
{
    return [
        $this->primaryKeyFactory->create('id'),
        $this->columnFactory->string('title', 255)->notNull(),
        
        // Reference the 'id' column in the 'authors' table
        $this->foreignKeyFactory->create('author_id', 'authors', 'id'),
        
        $this->dateCreatedFactory->create('created_at'),
    ];
}
```

**Customization:**

```php
// Allow NULL (optional foreign key)
$this->foreignKeyFactory->create('author_id', 'authors', 'id', nullable: true);

// Cascade deletes (delete posts when author is deleted)
$this->foreignKeyFactory->create('author_id', 'authors', 'id', onDelete: 'CASCADE');

// Set NULL on delete (orphan posts when author is deleted)
$this->foreignKeyFactory->create('author_id', 'authors', 'id', onDelete: 'SET NULL', nullable: true);
```

**When to use:**
* Relationships between tables (posts → authors, orders → customers)
* Enforcing referential integrity at the database level
* Junction tables (many-to-many relationships)

---

## Index Factory

Indexes improve query performance by allowing the database to find rows faster. The `IndexFactory` creates single-column and composite indexes.

### `IndexFactory::create()`

Creates a **single-column index**.

**Usage:**

```php
public function __construct(
    private IndexFactory $indexFactory
) {}

public function getIndexes(): array
{
    return [
        // Index on author_id for "find all posts by author" queries
        $this->indexFactory->create('idx_author', ['author_id']),
        
        // Index on published_date for sorting and range queries
        $this->indexFactory->create('idx_published', ['published_date']),
    ];
}
```

### `IndexFactory::composite()`

Creates a **composite index** that spans multiple columns. These are useful for queries that filter on multiple fields at once.

**Usage:**

```php
public function getIndexes(): array
{
    return [
        // Composite index for "posts by author, sorted by publish date"
        $this->indexFactory->composite('idx_author_date', ['author_id', 'published_date']),
    ];
}
```

**When to use composite indexes:**
* Queries with multiple `WHERE` conditions
* Queries that filter and sort on different columns
* Covering indexes (include all columns needed by a query)

**Note:** Column order matters. The index `['author_id', 'published_date']` can serve:
* `WHERE author_id = 123`
* `WHERE author_id = 123 ORDER BY published_date`

But not:
* `WHERE published_date > '2024-01-01'` (doesn't start with `author_id`)

---

## Real-World Example: Full Table with Factories

Here's a complete table that uses all the factories:

```php
<?php

use PHPNomad\Database\Interfaces\Table;
use PHPNomad\Database\Factories\Column;
use PHPNomad\Database\Factories\PrimaryKey;
use PHPNomad\Database\Factories\ForeignKey;
use PHPNomad\Database\Factories\DateCreated;
use PHPNomad\Database\Factories\DateModified;
use PHPNomad\Database\Factories\Index;

final class PostTable implements Table
{
    public function __construct(
        private Column $columnFactory,
        private PrimaryKeyFactory $primaryKeyFactory,
        private ForeignKeyFactory $foreignKeyFactory,
        private DateCreatedFactory $dateCreatedFactory,
        private DateModifiedFactory $dateModifiedFactory,
        private IndexFactory $indexFactory
    ) {}

    public function getTableName(): string
    {
        return 'posts';
    }

    public function getColumns(): array
    {
        return [
            // Auto-increment primary key
            $this->primaryKeyFactory->create('id'),
            
            // Regular columns
            $this->columnFactory->string('title', 255)->notNull(),
            $this->columnFactory->text('content')->notNull(),
            $this->columnFactory->string('slug', 255)->notNull()->unique(),
            $this->columnFactory->datetime('published_date')->nullable(),
            
            // Foreign key to authors table
            $this->foreignKeyFactory->create('author_id', 'authors', 'id'),
            
            // Timestamps
            $this->dateCreatedFactory->create('created_at'),
            $this->dateModifiedFactory->create('updated_at'),
        ];
    }

    public function getPrimaryKey(): PrimaryKey
    {
        return $this->primaryKeyFactory->create('id');
    }

    public function getIndexes(): array
    {
        return [
            // Single-column indexes
            $this->indexFactory->create('idx_author', ['author_id']),
            $this->indexFactory->create('idx_published', ['published_date']),
            $this->indexFactory->create('idx_slug', ['slug']), // unique already indexed, but explicit
            
            // Composite index for "author's published posts, sorted by date"
            $this->indexFactory->composite('idx_author_published', [
                'author_id',
                'published_date'
            ]),
        ];
    }
}
```

This table uses factories for:
* Primary key (`id`)
* Foreign key (`author_id`)
* Timestamps (`created_at`, `updated_at`)
* Indexes (single and composite)

The result is a clean, readable schema definition with minimal boilerplate.

## Best Practices

When using factories:

* **Inject factories via constructor** — let the DI container provide them.
* **Use factories for standard patterns** — don't manually define primary keys or timestamps.
* **Customize when needed** — factories accept parameters for common variations (nullable, cascade, etc.).
* **Name indexes descriptively** — use `idx_` prefix and column names (e.g., `idx_author_date`).
* **Add indexes on foreign keys** — always index columns used in joins.
* **Consider composite indexes** — they're more efficient than multiple single-column indexes for multi-condition queries.

## When NOT to Use Factories

Factories are for **common patterns**. For unique or domain-specific columns, use the base `Column` factory:

```php
// Custom columns that don't fit factory patterns
$this->columnFactory->decimal('price', 10, 2)->notNull(),
$this->columnFactory->json('metadata')->nullable(),
$this->columnFactory->enum('status', ['draft', 'published', 'archived'])->default('draft'),
```

Factories reduce boilerplate for **90% of columns**. The remaining 10% are domain-specific and should use `Column` directly.

## What's Next

To see how these factories are used in context, see:

* [Table Definitions](/packages/database/tables/introduction) — how factories compose into full table schemas
* [Individual Factory Docs](/packages/database/included-factories/primary-key-factory) — detailed API reference for each factory
* [Database Handlers](/packages/database/handlers/introduction) — how handlers use table definitions
