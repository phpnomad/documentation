---
id: database-introduction
slug: docs/packages/database/introduction
title: Database Package
doc_type: explanation
status: active
language: en
owner: docs-team
last_reviewed: 2025-01-08
applies_to: ["all"]
canonical: true
summary: The database package provides concrete database implementations of the datastore pattern with table schemas, query building, caching, and event broadcasting.
llm_summary: >
  phpnomad/database implements the datastore interfaces for SQL databases. Define table schemas, create handlers that
  query databases, leverage automatic caching and event broadcasting, and use query builders for complex conditions.
  Works with MySQL, MariaDB, and compatible databases.
questions_answered:
  - What is the database package?
  - What does phpnomad/database provide?
  - How does database persistence work?
  - What are the key concepts in database datastores?
  - When should I use the database package?
  - How does caching work in database handlers?
  - What events are broadcast?
audience:
  - developers
  - backend engineers
  - database developers
tags:
  - database
  - package-overview
  - persistence
llm_tags:
  - database-package
  - sql-persistence
  - database-handlers
keywords:
  - phpnomad database
  - database package
  - database persistence
  - SQL datastores
related:
  - ../../core-concepts/overview-and-architecture
  - ../../core-concepts/getting-started-tutorial
  - ../datastore/introduction
see_also:
  - handlers/introduction
  - tables/introduction
  - table-schema-definition
  - ../logger/introduction
noindex: false
---

# Database

`phpnomad/database` provides **concrete database implementations** of the datastore pattern. It's designed to let you define **table schemas, execute queries, and persist models** in SQL databases while maintaining the storage-agnostic abstractions from `phpnomad/datastore`.

At its core:

* **Table classes** define database schemas including columns, indices, and versioning.
* **Database handlers** implement datastore interfaces with actual SQL queries.
* **Query builders** construct SQL from condition arrays without writing raw queries.
* **Caching** automatically stores retrieved models to reduce database hits.
* **Event broadcasting** emits events when records are created, updated, or deleted.

By implementing the datastore interfaces with database-backed handlers, you get full CRUD operations, complex querying, caching, and event notifications—all while keeping your domain logic portable.

---

## Key ideas at a glance

* **DatabaseDatastoreHandler** — Base class for database-backed handlers that query tables.
* **Table** — Schema definition including columns, indices, and versioning for migrations.
* **QueryBuilder** — Constructs SQL queries from condition arrays and parameters.
* **CacheableService** — Automatic caching layer that stores retrieved models by identity.
* **EventStrategy** — Broadcasts RecordCreated, RecordUpdated, RecordDeleted events.
* **WithDatastoreHandlerMethods** — Trait providing complete CRUD implementation.

---

## The database persistence lifecycle

When your application performs a data operation through a database-backed datastore, the request flows through these layers:

```
Application → Datastore → DatabaseHandler → QueryBuilder → Database → ModelAdapter → Model
                                    ↓                ↑
                              Cache Check      Cache Store
                                    ↓
                              Event Broadcast
```

### Application layer

Your application calls methods on the Datastore interface:

```php
$post = $postDatastore->find(123);
$posts = $postDatastore->where([
    ['column' => 'status', 'operator' => '=', 'value' => 'published']
]);
```

### Datastore layer

The Datastore delegates to its database handler:

```php
class PostDatastore implements PostDatastoreInterface
{
    use WithDatastorePrimaryKeyDecorator;

    protected Datastore $datastoreHandler;

    public function __construct(PostDatabaseDatastoreHandler $datastoreHandler)
    {
        $this->datastoreHandler = $datastoreHandler;
    }
}
```

### Database handler layer

The **Database Handler** extends `IdentifiableDatabaseDatastoreHandler` and uses the `WithDatastoreHandlerMethods` trait to implement all standard operations:

```php
class PostDatabaseDatastoreHandler extends IdentifiableDatabaseDatastoreHandler 
    implements PostDatastoreHandler
{
    use WithDatastoreHandlerMethods;

    public function __construct(
        DatabaseServiceProvider $serviceProvider,
        PostsTable $table,
        PostAdapter $adapter,
        TableSchemaService $tableSchemaService
    ) {
        $this->serviceProvider = $serviceProvider;
        $this->table = $table;
        $this->modelAdapter = $adapter;
        $this->tableSchemaService = $tableSchemaService;
        $this->model = Post::class;
    }
}
```

### Cache check

Before querying the database, the handler checks the cache:

```php
$cacheKey = ['identities' => ['id' => 123], 'type' => Post::class];
if ($cached = $this->serviceProvider->cacheableService->get($cacheKey)) {
    return $cached; // Cache hit, skip database
}
```

### Query building

The handler uses `QueryBuilder` to construct SQL:

```php
$query = $this->serviceProvider->queryBuilder
    ->select()
    ->from($this->table)
    ->where('id', '=', 123)
    ->build();
```

The `QueryBuilder` generates parameterized SQL with placeholders to prevent injection.

### Database execution

The query executes against the database and returns raw rows:

```php
$row = $this->serviceProvider->queryStrategy->execute($query);
```

### Model conversion

The `ModelAdapter` converts the raw row to a model:

```php
$post = $this->modelAdapter->toModel($row);
```

### Cache storage

The model is stored in cache for future requests:

```php
$this->serviceProvider->cacheableService->set($cacheKey, $post);
```

### Event broadcasting

Events are broadcast after successful operations:

```php
// After create
$this->serviceProvider->eventStrategy->dispatch(new RecordCreated($post));

// After update
$this->serviceProvider->eventStrategy->dispatch(new RecordUpdated($post));

// After delete
$this->serviceProvider->eventStrategy->dispatch(new RecordDeleted($post));
```

---

## Why use the database package

### Automatic caching

Every find operation checks cache first. Subsequent requests for the same record return instantly without database queries. Cache invalidates automatically on updates and deletes.

### Event-driven architecture

Database operations broadcast events that other systems can listen to. Create audit logs, send notifications, update search indices, or trigger workflows—all decoupled from the handler.

### Query abstraction

No raw SQL in your handlers. Build queries with arrays and let `QueryBuilder` handle SQL generation, parameterization, and escaping.

### Schema versioning

Table definitions include version numbers. When schemas change, migrations can detect version differences and update tables accordingly.

### Standardized patterns

All database handlers follow the same pattern: extend the base, inject dependencies, implement interfaces. This consistency makes codebases predictable and maintainable.

---

## Core components

### Database handlers

Handlers extend `IdentifiableDatabaseDatastoreHandler` and use `WithDatastoreHandlerMethods` to implement CRUD operations. They connect table schemas to datastore interfaces.

See [Database Handlers](handlers/introduction) for complete documentation.

### Table schemas

Table classes extend `Table` and define columns, indices, and versioning. They specify how entities are stored in the database without writing DDL.

```php
class PostsTable extends Table
{
    public function getUnprefixedName(): string
    {
        return 'posts';
    }

    public function getColumns(): array
    {
        return [
            (new PrimaryKeyFactory())->toColumn(),
            new Column('title', 'VARCHAR', [255], 'NOT NULL'),
            new Column('content', 'TEXT', null, 'NOT NULL'),
            (new DateCreatedFactory())->toColumn(),
        ];
    }

    public function getIndices(): array
    {
        return [
            new Index(['title'], 'idx_posts_title'),
        ];
    }
}
```

See [Table Schema Definition](table-schema-definition) and [Tables](tables/introduction) for complete documentation.

### Query building

The `QueryBuilder` converts condition arrays and parameters into SQL queries. Conditions use a structured format that supports AND/OR logic, operators, and nested groups.

```php
$posts = $handler->where([
    [
        'type' => 'AND',
        'clauses' => [
            ['column' => 'status', 'operator' => '=', 'value' => 'published'],
            ['column' => 'views', 'operator' => '>', 'value' => 1000]
        ]
    ]
], limit: 10);
```

See [Query Building](query-building) for complete documentation.

### Caching and events

The database package includes automatic caching and event broadcasting. Models are cached by identity and invalidated on mutations. Events broadcast after successful operations.

See [Caching and Events](caching-and-events) for complete documentation.

### Database service provider

The `DatabaseServiceProvider` is injected into handlers and provides access to:

- `QueryBuilder` — Constructs SQL queries
- `CacheableService` — Caches models
- `EventStrategy` — Broadcasts events
- `ClauseBuilder` — Builds WHERE clauses
- `LoggerStrategy` — Logs operations
- `QueryStrategy` — Executes queries

See [DatabaseServiceProvider](database-service-provider) for complete documentation.

---

## Column and index factories

The database package provides factories for common column patterns:

- **PrimaryKeyFactory** — Auto-incrementing integer primary key
- **DateCreatedFactory** — Timestamp with `DEFAULT CURRENT_TIMESTAMP`
- **DateModifiedFactory** — Timestamp with `ON UPDATE CURRENT_TIMESTAMP`
- **ForeignKeyFactory** — Foreign key columns with constraints

```php
public function getColumns(): array
{
    return [
        (new PrimaryKeyFactory())->toColumn(),
        new Column('authorId', 'BIGINT', null, 'NOT NULL'),
        (new ForeignKeyFactory('author', 'authors', 'id'))->toColumn(),
        (new DateCreatedFactory())->toColumn(),
        (new DateModifiedFactory())->toColumn(),
    ];
}
```

See [Column and Index Factories](included-factories/introduction) for complete documentation.

---

## Junction tables

Many-to-many relationships use junction tables. The `JunctionTable` class automatically creates compound primary keys, foreign keys, and standard indices from two related tables.

```php
class PostsTagsTable extends JunctionTable
{
    public function __construct(
        // Base dependencies...
        PostsTable $leftTable,
        TagsTable $rightTable
    ) {
        parent::__construct(...func_get_args());
    }
}
```

See [Junction Tables](junction-tables) for complete documentation.

---

## Supported databases

The database package works with:

- MySQL 5.7+
- MariaDB 10.2+
- Other MySQL-compatible databases

The query builder generates standard SQL that should work across these systems. Platform-specific features (stored procedures, triggers, full-text search) are not abstracted.

---

## When to use this package

Use `phpnomad/database` when:

- You're storing data in a SQL database
- You want automatic caching and event broadcasting
- Query building and schema versioning are valuable
- You're using the datastore pattern with database persistence

If your data comes from REST APIs, GraphQL, or other non-database sources, you don't need this package. Use `phpnomad/datastore` and implement custom handlers.

---

## Package components

### Required reading

- **[Database Handlers](handlers/introduction)** — Creating database-backed handlers
- **[Table Schema Definition](table-schema-definition)** — Defining database tables
- **[Tables](tables/introduction)** — Table base classes and patterns

### Deep dives

- **[Query Building](query-building)** — Condition arrays, operators, QueryBuilder
- **[Caching and Events](caching-and-events)** — How caching and event broadcasting work
- **[DatabaseServiceProvider](database-service-provider)** — Services available to handlers

### Reference

- **[Column and Index Factories](included-factories/introduction)** — Pre-built column factories
- **[Junction Tables](junction-tables)** — Many-to-many relationships

---

## Relationship to other packages

- **[phpnomad/datastore](../datastore/introduction)** — Defines interfaces that database handlers implement
- **phpnomad/models** — Provides DataModel interface (covered in [Models and Identity](../../core-concepts/models-and-identity))
- **[phpnomad/event](../event/introduction)** — EventStrategy interface for broadcasting events
- **[phpnomad/logger](../logger/introduction)** — LoggerStrategy interface for operation logging

---

## Next steps

- **New to database datastores?** Start with [Getting Started Tutorial](../../core-concepts/getting-started-tutorial)
- **Ready to implement?** See [Database Handlers](handlers/introduction)
- **Need table schemas?** Check [Table Schema Definition](table-schema-definition)
- **Building complex queries?** Read [Query Building](query-building)
