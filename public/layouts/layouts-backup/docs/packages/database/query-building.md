# Query Building

PHPNomad's database package provides a **fluent query builder** for constructing safe, escaped SQL queries. The
`QueryBuilder` interface offers a chainable API for building SELECT queries with WHERE clauses, joins, grouping,
ordering, and pagination—without writing raw SQL.

Query building is used primarily in [database handlers](/packages/database/handlers/introduction) to execute queries
against tables defined by [table schemas](/packages/database/table-schema-definition).

## Core Components

### QueryBuilder

The main interface for building SELECT queries. Provides methods for:

* Selecting fields
* Setting FROM clause
* Adding WHERE conditions via `ClauseBuilder`
* JOINs (LEFT, RIGHT)
* Grouping and aggregations (SUM, COUNT)
* Ordering and pagination (ORDER BY, LIMIT, OFFSET)

### ClauseBuilder

A specialized builder for constructing WHERE clauses with:

* Multiple conditions (AND, OR)
* Comparison operators (=, <, >, IN, LIKE, BETWEEN, etc.)
* Grouped conditions (parentheses)
* Proper escaping and sanitization

---

## Basic Query Building

### Simple SELECT Query

```php
<?php

use PHPNomad\Database\Interfaces\QueryBuilder;
use PHPNomad\Database\Interfaces\Table;

class PostHandler
{
    public function __construct(
        private QueryBuilder $queryBuilder,
        private PostsTable $table
    ) {}

    public function getAll(): array
    {
        $sql = $this->queryBuilder
            ->select('id', 'title', 'content')
            ->from($this->table)
            ->build();
        
        // Execute query and return results
        return $this->executeQuery($sql);
    }
}
```

**Generated SQL:**

```sql
SELECT id, title, content FROM wp_posts
```

---

### SELECT with WHERE Clause

```php
public function getPostsByAuthor(int $authorId): array
{
    $clause = $this->clauseBuilder
        ->useTable($this->table)
        ->where('author_id', '=', $authorId);

    $sql = $this->queryBuilder
        ->select('*')
        ->from($this->table)
        ->where($clause)
        ->build();
    
    return $this->executeQuery($sql);
}
```

**Generated SQL:**

```sql
SELECT * FROM wp_posts WHERE author_id = 123
```

---

## ClauseBuilder API

The `ClauseBuilder` constructs WHERE clauses with proper escaping.

### Comparison Operators

**Equality:**

```php
$clause->where('status', '=', 'published');
// WHERE status = 'published'
```

**Inequality:**

```php
$clause->where('view_count', '>', 100);
// WHERE view_count > 100

$clause->where('view_count', '>=', 50);
// WHERE view_count >= 50

$clause->where('view_count', '<', 1000);
// WHERE view_count < 1000
```

**IN operator:**

```php
$clause->where('status', 'IN', 'published', 'featured', 'archived');
// WHERE status IN ('published', 'featured', 'archived')
```

**NOT IN:**

```php
$clause->where('status', 'NOT IN', 'draft', 'pending');
// WHERE status NOT IN ('draft', 'pending')
```

**LIKE operator:**

```php
$clause->where('title', 'LIKE', '%wordpress%');
// WHERE title LIKE '%wordpress%'
```

**BETWEEN:**

```php
$clause->where('created_at', 'BETWEEN', '2024-01-01', '2024-12-31');
// WHERE created_at BETWEEN '2024-01-01' AND '2024-12-31'
```

**IS NULL / IS NOT NULL:**

```php
$clause->where('published_date', 'IS NULL');
// WHERE published_date IS NULL

$clause->where('published_date', 'IS NOT NULL');
// WHERE published_date IS NOT NULL
```

---

### Chaining Conditions

**AND conditions:**

```php
$clause = $this->clauseBuilder
    ->useTable($this->table)
    ->where('author_id', '=', 123)
    ->andWhere('status', '=', 'published')
    ->andWhere('view_count', '>', 100);

// WHERE author_id = 123 AND status = 'published' AND view_count > 100
```

**OR conditions:**

```php
$clause = $this->clauseBuilder
    ->useTable($this->table)
    ->where('status', '=', 'published')
    ->orWhere('status', '=', 'featured');

// WHERE status = 'published' OR status = 'featured'
```

**Mixed AND/OR:**

```php
$clause = $this->clauseBuilder
    ->useTable($this->table)
    ->where('author_id', '=', 123)
    ->andWhere('status', '=', 'published')
    ->orWhere('status', '=', 'featured');

// WHERE author_id = 123 AND status = 'published' OR status = 'featured'
// Note: Operator precedence applies (AND before OR)
```

---

### Grouped Conditions

For complex logic with parentheses, use `group()`:

```php
// (status = 'published' OR status = 'featured') AND author_id = 123

$statusClause = $this->clauseBuilder
    ->useTable($this->table)
    ->where('status', '=', 'published')
    ->orWhere('status', '=', 'featured');

$clause = $this->clauseBuilder
    ->useTable($this->table)
    ->group('AND', $statusClause)
    ->andWhere('author_id', '=', 123);
```

**More complex grouping:**

```php
// (author_id = 123 OR author_id = 456) AND (status = 'published' OR status = 'featured')

$authorClause = $this->clauseBuilder
    ->useTable($this->table)
    ->where('author_id', '=', 123)
    ->orWhere('author_id', '=', 456);

$statusClause = $this->clauseBuilder
    ->useTable($this->table)
    ->where('status', '=', 'published')
    ->orWhere('status', '=', 'featured');

$clause = $this->clauseBuilder
    ->useTable($this->table)
    ->group('AND', $authorClause)
    ->andGroup('AND', $statusClause);
```

---

## QueryBuilder Methods

### select()

Specify columns to retrieve:

```php
$queryBuilder->select('id', 'title', 'content');
// SELECT id, title, content

$queryBuilder->select('*');
// SELECT *
```

---

### from()

Set the table for the query:

```php
$queryBuilder->from($this->table);
// FROM wp_posts (using table's prefixed name)
```

---

### where()

Add a WHERE clause using a `ClauseBuilder`:

```php
$clause = $this->clauseBuilder
    ->useTable($this->table)
    ->where('author_id', '=', 123);

$queryBuilder->where($clause);
// WHERE author_id = 123
```

To remove a WHERE clause:

```php
$queryBuilder->where(null);
```

---

### leftJoin() / rightJoin()

Join tables:

```php
$queryBuilder
    ->select('posts.id', 'posts.title', 'users.name as author_name')
    ->from($this->postsTable)
    ->leftJoin($this->usersTable, 'posts.author_id', 'users.id');

// SELECT posts.id, posts.title, users.name as author_name
// FROM wp_posts
// LEFT JOIN wp_users ON posts.author_id = users.id
```

---

### groupBy()

Group results:

```php
$queryBuilder
    ->select('author_id')
    ->from($this->table)
    ->groupBy('author_id');

// SELECT author_id FROM wp_posts GROUP BY author_id
```

Multiple columns:

```php
$queryBuilder->groupBy('author_id', 'status');
// GROUP BY author_id, status
```

---

### Aggregations: sum() and count()

**COUNT:**

```php
$queryBuilder
    ->count('id', 'total_posts')
    ->from($this->table);

// SELECT COUNT(id) as total_posts FROM wp_posts
```

**SUM:**

```php
$queryBuilder
    ->sum('view_count', 'total_views')
    ->from($this->table);

// SELECT SUM(view_count) as total_views FROM wp_posts
```

**With GROUP BY:**

```php
$queryBuilder
    ->select('author_id')
    ->count('id', 'post_count')
    ->from($this->table)
    ->groupBy('author_id');

// SELECT author_id, COUNT(id) as post_count FROM wp_posts GROUP BY author_id
```

---

### orderBy()

Sort results:

```php
$queryBuilder->orderBy('published_date', 'DESC');
// ORDER BY published_date DESC

$queryBuilder->orderBy('title', 'ASC');
// ORDER BY title ASC
```

---

### limit() and offset()

Pagination:

```php
$queryBuilder
    ->select('*')
    ->from($this->table)
    ->limit(10)
    ->offset(20);

// SELECT * FROM wp_posts LIMIT 10 OFFSET 20
```

---

## Complete Query Example

Here's a complex query demonstrating multiple features:

```php
public function getPublishedPostsByAuthorsWithHighViews(
    array $authorIds,
    int $minViews,
    int $page = 1,
    int $perPage = 10
): array {
    // Build WHERE clause
    $clause = $this->clauseBuilder
        ->useTable($this->table)
        ->where('author_id', 'IN', ...$authorIds)
        ->andWhere('status', '=', 'published')
        ->andWhere('view_count', '>=', $minViews)
        ->andWhere('published_date', 'IS NOT NULL');

    // Build full query
    $sql = $this->queryBuilder
        ->select('id', 'title', 'author_id', 'view_count', 'published_date')
        ->from($this->table)
        ->where($clause)
        ->orderBy('view_count', 'DESC')
        ->limit($perPage)
        ->offset(($page - 1) * $perPage)
        ->build();

    return $this->executeQuery($sql);
}
```

**Generated SQL:**

```sql
SELECT id, title, author_id, view_count, published_date
FROM wp_posts
WHERE author_id IN (123, 456, 789)
  AND status = 'published'
  AND view_count >= 100
  AND published_date IS NOT NULL
ORDER BY view_count DESC
LIMIT 10 OFFSET 20
```

---

## Query Builder Reset

Reuse a query builder instance by resetting it:

```php
$queryBuilder->reset();
// Clears all clauses and returns to default state

$queryBuilder->resetClauses('where', 'limit', 'offset');
// Clears specific clauses only
```

---

## Using QueryBuilder in Handlers

Handlers receive `QueryBuilder` and `ClauseBuilder` from the `DatabaseServiceProvider`:

```php
<?php

class PostHandler extends IdentifiableDatabaseDatastoreHandler
{
    private QueryBuilder $queryBuilder;
    private ClauseBuilder $clauseBuilder;

    public function __construct(
        DatabaseServiceProvider $serviceProvider,
        PostsTable $table,
        PostAdapter $adapter
    ) {
        $this->queryBuilder = $serviceProvider->queryBuilder;
        $this->clauseBuilder = $serviceProvider->clauseBuilder;
        $this->table = $table;
        $this->adapter = $adapter;
    }

    public function findPublished(): array
    {
        $clause = $this->clauseBuilder
            ->useTable($this->table)
            ->where('status', '=', 'published');

        $sql = $this->queryBuilder
            ->select('*')
            ->from($this->table)
            ->where($clause)
            ->build();

        $rows = $this->executeQuery($sql);
        
        return array_map(
            fn($row) => $this->adapter->toModel($row),
            $rows
        );
    }
}
```

---

## Best Practices

### Always Use ClauseBuilder for WHERE Clauses

```php
// ✅ GOOD: proper escaping via ClauseBuilder
$clause = $this->clauseBuilder
    ->useTable($this->table)
    ->where('author_id', '=', $userInput);

$queryBuilder->where($clause);

// ❌ BAD: manual string concatenation (SQL injection risk!)
$sql = "WHERE author_id = " . $userInput;
```

### Build Queries, Don't Execute Raw SQL

```php
// ✅ GOOD: use query builder
$sql = $this->queryBuilder
    ->select('*')
    ->from($this->table)
    ->build();

// ❌ BAD: raw SQL strings
$sql = "SELECT * FROM wp_posts WHERE author_id = " . $id;
```

### Use Table Objects for FROM and JOIN

```php
// ✅ GOOD: table object handles prefixes
$queryBuilder->from($this->postsTable);

// ❌ BAD: hardcoded table name
$queryBuilder->from('wp_posts');
```

### Reset Builders Between Queries

```php
// ✅ GOOD: reset before reusing
$queryBuilder->reset();
$queryBuilder->select('*')->from($this->table);

// ❌ BAD: reusing without reset (accumulates clauses)
$queryBuilder->select('id');  // First query
$queryBuilder->select('*');   // Adds to first query!
```

### Validate User Input Before Queries

```php
// ✅ GOOD: validate before building query
if (!in_array($status, ['draft', 'published', 'archived'])) {
    throw new ValidationException("Invalid status");
}

$clause->where('status', '=', $status);

// ClauseBuilder handles escaping, but validation prevents logic errors
```

---

## What's Next

* [Database Handlers](/packages/database/handlers/introduction) — how handlers use query builders
* [Table Schema Definition](/packages/database/table-schema-definition) — defining tables for queries
* [Caching and Events](/packages/database/caching-and-events) — query result caching
