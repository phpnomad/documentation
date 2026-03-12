# Tables

Tables in PHPNomad are **schema definitions** that describe the structure of your database tables. They define columns, indexes, primary keys, and constraints in a **database-agnostic** way, allowing handlers and query builders to generate the correct SQL for your target database.

A table object is not a query builder or active record. It's a **metadata container** that describes what a table looks like, which [handlers](/packages/database/handlers/introduction) use to create schemas and [QueryBuilder](/packages/database/query-building) uses to generate queries.

## What Tables Define

A table definition specifies:

* **Table name** — the name of the table in the database.
* **Columns** — each field's name, type, and constraints (nullable, default value, etc.).
* **Primary key** — which column(s) uniquely identify rows.
* **Indexes** — additional indexes for query performance.
* **Foreign keys** — relationships to other tables (optional).

These definitions are created using **factory classes** from the `phpnomad/database` package, which provide a fluent API for building schema definitions.

## Why Table Objects Exist

In PHPNomad, **schema lives in code**, not in migration scripts or raw SQL. This has several benefits:

* **Portability** — the same table definition works across MySQL, MariaDB, and other supported databases.
* **Versioning** — schema changes are tracked in version control alongside code.
* **Testability** — tables can be created in test databases programmatically.
* **Type safety** — column definitions are strongly typed and validated at runtime.

Handlers use table definitions to:
* Create tables on first use (or during migrations).
* Validate that models match the schema.
* Generate queries that reference the correct columns.

## The Base Table Class

The `Table` class is the **standard base** for defining entity tables. You extend it and provide column definitions, a primary key, and optional indexes.

### Basic example

```php
<?php

use PHPNomad\Database\Interfaces\Table;
use PHPNomad\Database\Factories\Column;
use PHPNomad\Database\Factories\PrimaryKey;

final class PostTable implements Table
{
    public function __construct(
        private Column $columnFactory,
        private PrimaryKey $primaryKeyFactory
    ) {}

    public function getTableName(): string
    {
        return 'posts';
    }

    public function getColumns(): array
    {
        return [
            $this->columnFactory->int('id')->autoIncrement(),
            $this->columnFactory->string('title', 255)->notNull(),
            $this->columnFactory->text('content')->notNull(),
            $this->columnFactory->int('author_id')->notNull(),
            $this->columnFactory->datetime('published_date')->nullable(),
            $this->columnFactory->datetime('created_at')->default('CURRENT_TIMESTAMP'),
            $this->columnFactory->datetime('updated_at')->default('CURRENT_TIMESTAMP')->onUpdate('CURRENT_TIMESTAMP'),
        ];
    }

    public function getPrimaryKey(): PrimaryKey
    {
        return $this->primaryKeyFactory->create('id');
    }

    public function getIndexes(): array
    {
        return [
            // Add index on author_id for faster lookups
            $this->indexFactory->create('idx_author', ['author_id']),
        ];
    }
}
```

This defines a `posts` table with:
* An auto-increment `id` primary key
* Required `title`, `content`, and `author_id` columns
* Optional `published_date` column
* Auto-managed `created_at` and `updated_at` timestamps

## Column Factories

Column definitions are created using factory methods that return `Column` objects. PHPNomad provides [several included factories](/packages/database/included-factories/introduction) for common patterns:

* **`PrimaryKeyFactory`** — creates auto-increment integer primary keys
* **`DateCreatedFactory`** — creates `created_at` timestamp columns
* **`DateModifiedFactory`** — creates `updated_at` columns with auto-update
* **`ForeignKeyFactory`** — creates foreign key columns that reference other tables

You can also use the base `Column` factory to define custom columns with full control over type, size, nullability, defaults, and constraints.

## Junction Tables

PHPNomad provides a specialized `JunctionTable` class for **many-to-many relationships**. Junction tables store associations between two entities (e.g., posts and tags) without additional data.

A junction table:
* Has a **compound primary key** (both foreign keys together).
* Stores only the foreign keys (no additional columns).
* Uses composite indexes for efficient lookups in both directions.

### Example: PostTag junction table

```php
<?php

use PHPNomad\Database\Interfaces\JunctionTable;
use PHPNomad\Database\Factories\ForeignKey;

final class PostTagTable implements JunctionTable
{
    public function __construct(
        private ForeignKey $foreignKeyFactory
    ) {}

    public function getTableName(): string
    {
        return 'post_tags';
    }

    public function getColumns(): array
    {
        return [
            $this->foreignKeyFactory->create('post_id', 'posts', 'id'),
            $this->foreignKeyFactory->create('tag_id', 'tags', 'id'),
        ];
    }

    public function getPrimaryKey(): array
    {
        return ['post_id', 'tag_id']; // Compound key
    }

    public function getIndexes(): array
    {
        return [
            // Index for "which tags are on this post?"
            $this->indexFactory->create('idx_post', ['post_id']),
            // Index for "which posts have this tag?"
            $this->indexFactory->create('idx_tag', ['tag_id']),
        ];
    }
}
```

Junction tables are used with the [JunctionTable class](/packages/database/tables/junction-table-class) to manage many-to-many relationships efficiently.

## Table Lifecycle

Tables are:

1. **Defined** — you create a class that implements `Table` and describes the schema.
2. **Injected** — your handler receives the table instance via constructor DI.
3. **Used** — handlers call `getTableName()`, `getColumns()`, etc. to generate queries.
4. **Created** — on first use (or during migrations), the handler ensures the table exists in the database.

You don't "run" a table or call methods on it directly. It's a **passive descriptor** that other components consume.

## Column Types and Constraints

The `Column` factory supports these types:

* `int(name, size)` — integers (various sizes: TINYINT, INT, BIGINT)
* `string(name, length)` — VARCHAR columns
* `text(name)` — TEXT columns (arbitrary length)
* `datetime(name)` — DATETIME columns
* `boolean(name)` — BOOLEAN columns
* `json(name)` — JSON columns (database-dependent)
* `decimal(name, precision, scale)` — DECIMAL columns

And these constraints:

* `notNull()` — column cannot be NULL
* `nullable()` — column can be NULL (default)
* `default(value)` — default value when not specified
* `autoIncrement()` — auto-incrementing integer (usually on primary keys)
* `onUpdate(value)` — value to set on UPDATE (e.g., `CURRENT_TIMESTAMP`)
* `unique()` — enforce uniqueness constraint

Chaining these methods produces expressive column definitions:

```php
$this->columnFactory
    ->string('email', 255)
    ->notNull()
    ->unique();
```

## Primary Keys

Every table must define a primary key. Most tables use a **single auto-increment integer**:

```php
public function getPrimaryKey(): PrimaryKey
{
    return $this->primaryKeyFactory->create('id');
}
```

Tables with **compound primary keys** (like junction tables) return an array:

```php
public function getPrimaryKey(): array
{
    return ['user_id', 'session_token'];
}
```

## Indexes

Indexes improve query performance by allowing the database to find rows faster. Add indexes on:

* Foreign keys (for joins)
* Columns used in WHERE clauses
* Columns used for sorting

**Example: adding indexes**

```php
public function getIndexes(): array
{
    return [
        $this->indexFactory->create('idx_author', ['author_id']),
        $this->indexFactory->create('idx_published', ['published_date']),
        $this->indexFactory->composite('idx_author_date', ['author_id', 'published_date']),
    ];
}
```

Composite indexes support queries that filter on multiple columns.

## Best Practices

When defining tables:

* **Use factories** — don't construct `Column` objects manually; use the provided factories.
* **Name consistently** — use snake_case for column names to match database conventions.
* **Index foreign keys** — always add indexes on columns used in joins.
* **Use timestamps** — include `created_at` and `updated_at` for audit trails.
* **Keep tables focused** — each table should represent one entity or one relationship (junction tables).
* **Declare constraints** — use `notNull()`, `unique()`, etc. to enforce data integrity at the database level.

## Schema Evolution

When your schema changes (adding columns, indexes, etc.), update the table definition. The handler will detect changes and can update the database schema, though this depends on your migration strategy.

For production systems, consider:
* **Versioned migrations** — track schema changes explicitly.
* **Backwards compatibility** — add columns as nullable first, backfill data, then mark as not-null.
* **Index creation** — add indexes separately from table creation if tables are large.

## What's Next

To understand how tables fit into the larger system, see:

* [Table Class](/packages/database/tables/table-class) — detailed API reference for entity tables
* [JunctionTable Class](/packages/database/tables/junction-table-class) — many-to-many relationship tables
* [Included Factories](/packages/database/included-factories/introduction) — pre-built column factories
* [Database Handlers](/packages/database/handlers/introduction) — how handlers use table definitions
