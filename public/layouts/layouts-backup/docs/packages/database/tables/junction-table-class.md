# JunctionTable Class

The `JunctionTable` class extends `Table` for defining **many-to-many relationship tables**. Junction tables store associations between two entities using foreign keys and compound primary keys.

For conceptual overview and usage patterns, see [Junction Tables](/packages/database/junction-tables).

## Key Differences from Table

Junction tables differ from regular entity tables in that they:

* Have **compound primary keys** (multiple columns)
* Store only **foreign keys** (no additional data by default)
* Use **composite indexes** for bidirectional lookups

## Example

```php
<?php

namespace App\Service\Datastores\PostTag;

use PHPNomad\Database\Abstracts\Table;
use PHPNomad\Database\Factories\Column;
use PHPNomad\Database\Factories\Index;

class PostTagsTable extends Table
{
    public function getUnprefixedName(): string
    {
        return 'post_tags';
    }

    public function getSingularUnprefixedName(): string
    {
        return 'post_tag';
    }

    public function getAlias(): string
    {
        return 'pt';
    }

    public function getTableVersion(): string
    {
        return '1';
    }

    public function getColumns(): array
    {
        return [
            new Column('post_id', 'BIGINT', null, 'NOT NULL'),
            new Column('tag_id', 'BIGINT', null, 'NOT NULL'),
        ];
    }

    public function getIndices(): array
    {
        return [
            // Compound primary key
            new Index(['post_id', 'tag_id'], 'primary', 'PRIMARY KEY'),
            
            // Indexes for both directions
            new Index(['post_id'], 'post_idx', 'INDEX'),
            new Index(['tag_id'], 'tag_idx', 'INDEX'),
        ];
    }
}
```

## What's Next

* [Junction Tables](/packages/database/junction-tables) — complete guide
* [Table Class](/packages/database/tables/table-class) — entity tables
