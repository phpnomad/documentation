# Table Schema Definition

Table schema definitions describe the structure of your database tables in a database-agnostic way. They're PHP classes that extend `Table` and define columns, indexes, primary keys, and versioning information. Handlers use these definitions to create tables and generate queries.

This document provides a complete reference for defining table schemas in PHPNomad.

## Table Base Class

All tables extend `PHPNomad\Database\Abstracts\Table` and must implement six methods:

```php
<?php

namespace App\Service\Datastores\Post;

use PHPNomad\Database\Abstracts\Table;
use PHPNomad\Database\Factories\Column;
use PHPNomad\Database\Factories\Index;

class PostsTable extends Table
{
    public function getUnprefixedName(): string
    {
        return 'posts';  // Plural table name
    }

    public function getSingularUnprefixedName(): string
    {
        return 'post';  // Singular for model naming
    }

    public function getAlias(): string
    {
        return 'posts';  // SQL alias for queries
    }

    public function getTableVersion(): string
    {
        return '1';  // Increment on schema changes
    }

    public function getColumns(): array
    {
        return [
            // Column definitions
        ];
    }

    public function getIndices(): array
    {
        return [
            // Index definitions
        ];
    }
}
```

---

## Table Methods

### `getUnprefixedName(): string`

Returns the **plural** table name without any prefix. This is the actual database table name (after prefixes are applied).

```php
public function getUnprefixedName(): string
{
    return 'posts';  // Table will be wp_posts in WordPress
}
```

**When to use:** This is the primary table identifier.

---

### `getSingularUnprefixedName(): string`

Returns the **singular** form of the table name. Used for model naming conventions and relationship inference.

```php
public function getSingularUnprefixedName(): string
{
    return 'post';  // Singular form
}
```

**When to use:** Helps PHPNomad infer naming patterns.

---

### `getAlias(): string`

Returns a **short alias** for use in SQL queries. Keep it short (3-6 characters) to make generated SQL readable.

```php
public function getAlias(): string
{
    return 'posts';  // Used in SELECT posts.id, posts.title...
}
```

**Examples:**
* `posts` → `posts`
* `program_groups` → `pggrp`
* `programs_program_groups` → `pgmpggrp`

---

### `getTableVersion(): string`

Returns a **version string** for schema migration tracking. Increment this when you change the schema.

```php
public function getTableVersion(): string
{
    return '1';  // Increment to '2' when schema changes
}
```

**When to increment:**
* Adding/removing columns
* Changing column types
* Adding/removing indexes
* Modifying constraints

---

### `getColumns(): array`

Returns an array of `Column` objects defining the table structure.

```php
public function getColumns(): array
{
    return [
        (new PrimaryKeyFactory())->toColumn(),
        new Column('title', 'VARCHAR', [255], 'NOT NULL'),
        new Column('content', 'TEXT', null, 'NOT NULL'),
        (new DateCreatedFactory())->toColumn(),
    ];
}
```

See **Column Definitions** section below for details.

---

### `getIndices(): array`

Returns an array of `Index` objects defining database indexes.

```php
public function getIndices(): array
{
    return [
        new Index(['author_id'], 'author_idx', 'INDEX'),
        new Index(['slug'], 'slug_idx', 'UNIQUE'),
    ];
}
```

See **Index Definitions** section below for details.

---

## Column Definitions

Columns are defined using the `Column` class or specialized factories.

### Using the Column Class

```php
new Column(
    string $name,           // Column name
    string $type,           // SQL type (VARCHAR, INT, TEXT, etc.)
    array|null $params,     // Type parameters (e.g., [255] for VARCHAR)
    string $constraint      // Constraints (NOT NULL, NULL, DEFAULT, etc.)
)
```

### Column Types

**String types:**
```php
new Column('title', 'VARCHAR', [255], 'NOT NULL')
new Column('content', 'TEXT', null, 'NOT NULL')
new Column('slug', 'VARCHAR', [255], 'NOT NULL')
```

**Integer types:**
```php
new Column('id', 'INT', [11], 'NOT NULL AUTO_INCREMENT')
new Column('author_id', 'BIGINT', null, 'NOT NULL')
new Column('view_count', 'INT', [11], 'DEFAULT 0')
new Column('is_active', 'TINYINT', [1], 'DEFAULT 1')  // Boolean
```

**Date/Time types:**
```php
new Column('published_date', 'DATETIME', null, 'NULL')
new Column('created_at', 'TIMESTAMP', null, 'DEFAULT CURRENT_TIMESTAMP')
new Column('updated_at', 'TIMESTAMP', null, 'DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP')
```

**Other types:**
```php
new Column('price', 'DECIMAL', [10, 2], 'NOT NULL')  // 10 digits, 2 decimals
new Column('metadata', 'JSON', null, 'NULL')  // MySQL 5.7.8+
new Column('file_data', 'BLOB', null, 'NULL')
new Column('status', 'ENUM', ['draft', 'published', 'archived'], 'DEFAULT "draft"')
```

### Column Constraints

**NOT NULL / NULL:**
```php
'NOT NULL'  // Required field
'NULL'      // Optional field
```

**DEFAULT values:**
```php
'DEFAULT 0'
'DEFAULT "draft"'
'DEFAULT CURRENT_TIMESTAMP'
```

**AUTO_INCREMENT:**
```php
'NOT NULL AUTO_INCREMENT'  // For primary keys
```

**ON UPDATE:**
```php
'DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'  // Auto-update timestamp
```

**UNIQUE:**
```php
'NOT NULL UNIQUE'  // Unique constraint
```

---

## Column Factories

PHPNomad provides specialized factories for common column patterns. See [Included Factories](/packages/database/included-factories/introduction) for full details.

### PrimaryKeyFactory

Creates standard auto-increment integer primary keys:

```php
use PHPNomad\Database\Factories\Columns\PrimaryKeyFactory;

public function getColumns(): array
{
    return [
        (new PrimaryKeyFactory())->toColumn(),  // Creates 'id' INT AUTO_INCREMENT
        // other columns...
    ];
}
```

**Generates:**
```sql
id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY
```

---

### DateCreatedFactory

Creates timestamp columns with automatic creation date:

```php
use PHPNomad\Database\Factories\Columns\DateCreatedFactory;

public function getColumns(): array
{
    return [
        (new DateCreatedFactory())->toColumn(),  // Creates 'created_at'
    ];
}
```

**Generates:**
```sql
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
```

---

### DateModifiedFactory

Creates timestamp columns that auto-update on record modification:

```php
use PHPNomad\Database\Factories\Columns\DateModifiedFactory;

public function getColumns(): array
{
    return [
        (new DateModifiedFactory())->toColumn(),  // Creates 'updated_at'
    ];
}
```

**Generates:**
```sql
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL
```

---

### ForeignKeyFactory

Creates foreign key columns with optional constraints:

```php
use PHPNomad\Database\Factories\Columns\ForeignKeyFactory;

public function getColumns(): array
{
    return [
        (new ForeignKeyFactory(
            'author_id',      // Column name
            'users',          // Referenced table
            'id',             // Referenced column
            'CASCADE',        // ON DELETE action
            'CASCADE'         // ON UPDATE action
        ))->toColumn(),
    ];
}
```

**Common foreign key patterns:**
```php
// Required foreign key
(new ForeignKeyFactory('author_id', 'users', 'id'))->toColumn()

// Optional foreign key (NULL allowed)
(new ForeignKeyFactory('author_id', 'users', 'id', 'SET NULL'))->toColumn()

// Cascade deletes
(new ForeignKeyFactory('post_id', 'posts', 'id', 'CASCADE'))->toColumn()
```

---

## Index Definitions

Indexes improve query performance. Define them using the `Index` class:

```php
new Index(
    array $columns,      // Column(s) to index
    string $name,        // Index name
    string $type         // Index type: 'INDEX', 'UNIQUE', 'PRIMARY KEY'
)
```

### Single-Column Indexes

```php
public function getIndices(): array
{
    return [
        new Index(['author_id'], 'author_idx', 'INDEX'),
        new Index(['published_date'], 'published_idx', 'INDEX'),
    ];
}
```

**When to add:**
* Columns used in WHERE clauses
* Foreign key columns
* Columns used for sorting (ORDER BY)

---

### Unique Indexes

```php
public function getIndices(): array
{
    return [
        new Index(['email'], 'email_unique', 'UNIQUE'),
        new Index(['slug'], 'slug_unique', 'UNIQUE'),
    ];
}
```

**When to add:**
* Fields that must be unique across records
* Natural keys (email, username, slug)

---

### Composite Indexes

Indexes spanning multiple columns for complex queries:

```php
public function getIndices(): array
{
    return [
        new Index(['author_id', 'published_date'], 'author_published_idx', 'INDEX'),
        new Index(['user_id', 'session_token'], 'user_session_idx', 'PRIMARY KEY'),
    ];
}
```

**When to add:**
* Queries filtering on multiple columns
* Compound primary keys (junction tables)
* Queries with sorting on filtered data

**Column order matters:**
```php
new Index(['author_id', 'published_date'], 'idx', 'INDEX')
// Supports:  WHERE author_id = 123
// Supports:  WHERE author_id = 123 ORDER BY published_date
// Does NOT support: WHERE published_date > '2024-01-01' (doesn't start with author_id)
```

---

### Primary Key Indexes

For compound primary keys (typically junction tables):

```php
public function getIndices(): array
{
    return [
        new Index(['post_id', 'tag_id'], 'primary', 'PRIMARY KEY'),
    ];
}
```

---

## Complete Example

Here's a full table definition with all features:

```php
<?php

namespace App\Service\Datastores\Post;

use PHPNomad\Database\Abstracts\Table;
use PHPNomad\Database\Factories\Column;
use PHPNomad\Database\Factories\Index;
use PHPNomad\Database\Factories\Columns\PrimaryKeyFactory;
use PHPNomad\Database\Factories\Columns\DateCreatedFactory;
use PHPNomad\Database\Factories\Columns\DateModifiedFactory;
use PHPNomad\Database\Factories\Columns\ForeignKeyFactory;

class PostsTable extends Table
{
    public function getUnprefixedName(): string
    {
        return 'posts';
    }

    public function getSingularUnprefixedName(): string
    {
        return 'post';
    }

    public function getAlias(): string
    {
        return 'posts';
    }

    public function getTableVersion(): string
    {
        return '1';
    }

    public function getColumns(): array
    {
        return [
            // Primary key
            (new PrimaryKeyFactory())->toColumn(),
            
            // Regular columns
            new Column('title', 'VARCHAR', [255], 'NOT NULL'),
            new Column('slug', 'VARCHAR', [255], 'NOT NULL UNIQUE'),
            new Column('content', 'TEXT', null, 'NOT NULL'),
            new Column('excerpt', 'VARCHAR', [500], 'NULL'),
            
            // Enum for status
            new Column('status', 'ENUM', ['draft', 'published', 'archived'], 'DEFAULT "draft"'),
            
            // Numeric fields
            new Column('view_count', 'INT', [11], 'DEFAULT 0'),
            new Column('comment_count', 'INT', [11], 'DEFAULT 0'),
            
            // Foreign keys
            (new ForeignKeyFactory('author_id', 'users', 'id'))->toColumn(),
            (new ForeignKeyFactory('category_id', 'categories', 'id', 'SET NULL'))->toColumn(),
            
            // Dates
            new Column('published_date', 'DATETIME', null, 'NULL'),
            (new DateCreatedFactory())->toColumn(),
            (new DateModifiedFactory())->toColumn(),
        ];
    }

    public function getIndices(): array
    {
        return [
            // Single column indexes
            new Index(['author_id'], 'author_idx', 'INDEX'),
            new Index(['category_id'], 'category_idx', 'INDEX'),
            new Index(['status'], 'status_idx', 'INDEX'),
            new Index(['published_date'], 'published_idx', 'INDEX'),
            new Index(['slug'], 'slug_unique', 'UNIQUE'),
            
            // Composite indexes
            new Index(['author_id', 'published_date'], 'author_published_idx', 'INDEX'),
            new Index(['status', 'published_date'], 'status_published_idx', 'INDEX'),
        ];
    }
}
```

---

## Best Practices

### Column Naming

Use `snake_case` for column names to match database conventions:

```php
// ✅ GOOD
new Column('author_id', 'BIGINT', null, 'NOT NULL')
new Column('published_date', 'DATETIME', null, 'NULL')

// ❌ BAD
new Column('authorId', 'BIGINT', null, 'NOT NULL')
new Column('publishedDate', 'DATETIME', null, 'NULL')
```

### Index Foreign Keys

Always index foreign key columns for join performance:

```php
public function getColumns(): array
{
    return [
        (new ForeignKeyFactory('author_id', 'users', 'id'))->toColumn(),
    ];
}

public function getIndices(): array
{
    return [
        new Index(['author_id'], 'author_idx', 'INDEX'),  // ✅ Always add
    ];
}
```

### Use Factories for Standard Patterns

Don't manually define primary keys or timestamps:

```php
// ✅ GOOD
(new PrimaryKeyFactory())->toColumn()
(new DateCreatedFactory())->toColumn()

// ❌ BAD
new Column('id', 'INT', [11], 'NOT NULL AUTO_INCREMENT')
new Column('created_at', 'TIMESTAMP', null, 'DEFAULT CURRENT_TIMESTAMP')
```

### Version Your Schema

Increment `getTableVersion()` whenever you change the schema:

```php
// Initial version
public function getTableVersion(): string { return '1'; }

// After adding a column
public function getTableVersion(): string { return '2'; }

// After modifying an index
public function getTableVersion(): string { return '3'; }
```

### Nullable vs Required

Be explicit about nullability:

```php
// Required fields
new Column('title', 'VARCHAR', [255], 'NOT NULL')

// Optional fields
new Column('published_date', 'DATETIME', null, 'NULL')
```

### Index Strategy

Follow these guidelines:
1. **Always index:** Primary keys, foreign keys, unique fields
2. **Often index:** Columns in WHERE clauses, ORDER BY columns
3. **Consider composite indexes:** For multi-column queries
4. **Don't over-index:** Every index adds write overhead

---

## What's Next

* [Tables Introduction](/packages/database/tables/introduction) — overview of table definitions
* [Included Factories](/packages/database/included-factories/introduction) — column factory reference
* [Junction Tables](/packages/database/junction-tables) — many-to-many relationship tables
* [Database Handlers](/packages/database/handlers/introduction) — how handlers use table definitions
