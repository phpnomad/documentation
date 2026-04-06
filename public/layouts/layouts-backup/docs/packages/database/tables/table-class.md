# Table Class

The `Table` abstract class is the base for defining **entity table schemas** in PHPNomad. When you extend this class, you provide the column definitions, indexes, and metadata that describe how your entity is stored in the database.

This is a comprehensive reference for the `Table` API. For a tutorial introduction, see [Table Schema Definition](/packages/database/table-schema-definition).

## Abstract Class Definition

```php
abstract class Table
{
    abstract public function getUnprefixedName(): string;
    abstract public function getSingularUnprefixedName(): string;
    abstract public function getAlias(): string;
    abstract public function getTableVersion(): string;
    abstract public function getColumns(): array;
    abstract public function getIndices(): array;
}
```

## Required Methods

### `getUnprefixedName(): string`

Returns the plural table name without database prefix.

**Example:**
```php
public function getUnprefixedName(): string
{
    return 'posts';
}
```

### `getSingularUnprefixedName(): string`

Returns the singular form of the table name.

**Example:**
```php
public function getSingularUnprefixedName(): string
{
    return 'post';
}
```

### `getAlias(): string`

Returns a short alias for SQL queries.

**Example:**
```php
public function getAlias(): string
{
    return 'posts';
}
```

### `getTableVersion(): string`

Returns the schema version string. Increment when schema changes.

**Example:**
```php
public function getTableVersion(): string
{
    return '1';  // Increment to '2' after schema changes
}
```

### `getColumns(): array`

Returns array of Column definitions.

**Example:**
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

### `getIndices(): array`

Returns array of Index definitions.

**Example:**
```php
public function getIndices(): array
{
    return [
        new Index(['author_id'], 'author_idx', 'INDEX'),
        new Index(['slug'], 'slug_unique', 'UNIQUE'),
    ];
}
```

## Complete Example

```php
<?php

namespace App\Service\Datastores\Post;

use PHPNomad\Database\Abstracts\Table;
use PHPNomad\Database\Factories\Column;
use PHPNomad\Database\Factories\Index;
use PHPNomad\Database\Factories\Columns\PrimaryKeyFactory;
use PHPNomad\Database\Factories\Columns\DateCreatedFactory;
use PHPNomad\Database\Factories\Columns\DateModifiedFactory;

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
            (new PrimaryKeyFactory())->toColumn(),
            new Column('title', 'VARCHAR', [255], 'NOT NULL'),
            new Column('content', 'TEXT', null, 'NOT NULL'),
            new Column('author_id', 'BIGINT', null, 'NOT NULL'),
            new Column('published_date', 'DATETIME', null, 'NULL'),
            (new DateCreatedFactory())->toColumn(),
            (new DateModifiedFactory())->toColumn(),
        ];
    }

    public function getIndices(): array
    {
        return [
            new Index(['author_id'], 'author_idx', 'INDEX'),
            new Index(['published_date'], 'published_idx', 'INDEX'),
        ];
    }
}
```

## What's Next

* [Table Schema Definition](/packages/database/table-schema-definition) — complete schema reference
* [JunctionTable Class](/packages/database/tables/junction-table-class) — many-to-many tables
* [Tables Introduction](/packages/database/tables/introduction) — overview
