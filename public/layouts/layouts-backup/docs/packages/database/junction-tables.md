# Junction Tables and Many-to-Many Relationships

Junction tables (also called join tables or pivot tables) are used to represent **many-to-many relationships** between two entities. For example, posts can have many tags, and tags can be applied to many posts. A junction table stores these associations without duplicating data.

PHPNomad provides patterns for defining junction table schemas and working with many-to-many relationships through datastores.

## What is a Junction Table?

A junction table has:

* **Two foreign key columns** — one for each side of the relationship
* **Compound primary key** — both foreign keys together form the primary key
* **No additional data** — it only stores associations (for pure many-to-many)
* **Indexes on both columns** — for efficient lookups in both directions

### Example: Posts and Tags

**Scenario:** Posts can have multiple tags, and tags can be applied to multiple posts.

**Tables:**
* `posts` — stores post data (`id`, `title`, `content`, etc.)
* `tags` — stores tag data (`id`, `name`, `slug`, etc.)
* `post_tags` — junction table storing associations

**Relationship:**
```
posts (1) ←→ (many) post_tags (many) ←→ (1) tags
```

---

## Defining a Junction Table

Junction tables extend `Table` and follow a specific pattern:

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
        return 'post_tags';  // Plural form
    }

    public function getSingularUnprefixedName(): string
    {
        return 'post_tag';  // Singular form
    }

    public function getAlias(): string
    {
        return 'pt';  // Short alias for queries
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
            
            // Index for "which tags are on this post?"
            new Index(['post_id'], 'post_idx', 'INDEX'),
            
            // Index for "which posts have this tag?"
            new Index(['tag_id'], 'tag_idx', 'INDEX'),
        ];
    }
}
```

**Key features:**

1. **Two foreign key columns** — `post_id` and `tag_id`
2. **Compound primary key** — `['post_id', 'tag_id']` ensures uniqueness
3. **Two single-column indexes** — support queries in both directions

---

## Junction Table Naming Convention

Follow these conventions for consistency:

**Format:** `{entity1}_{entity2}` (alphabetical or logical order)

**Examples:**
* `post_tags` — posts to tags
* `user_roles` — users to roles
* `programs_program_groups` — programs to program groups
* `order_products` — orders to products

**Singular name:**
* `post_tag`
* `user_role`
* `program_program_group`

---

## Junction Table Model

Create a simple model representing the association:

```php
<?php

namespace App\Core\Models;

use PHPNomad\Datastore\Interfaces\DataModel;

class PostTag implements DataModel
{
    public function __construct(
        public readonly int $postId,
        public readonly int $tagId
    ) {}
}
```

**Note:** Junction models typically have no additional properties—just the two IDs that form the compound key.

---

## Junction Table Adapter

The adapter converts between the model and storage:

```php
<?php

namespace App\Core\Models\Adapters;

use PHPNomad\Datastore\Interfaces\DataModel;
use PHPNomad\Datastore\Interfaces\ModelAdapter;
use PHPNomad\Utils\Helpers\Arr;
use App\Core\Models\PostTag;

class PostTagAdapter implements ModelAdapter
{
    public function toArray(DataModel $model): array
    {
        return [
            'post_id' => $model->postId,
            'tag_id' => $model->tagId,
        ];
    }

    public function toModel(array $array): DataModel
    {
        return new PostTag(
            postId: Arr::get($array, 'post_id'),
            tagId: Arr::get($array, 'tag_id')
        );
    }
}
```

---

## Junction Table Datastore

Define the datastore interface and implementation:

### Interface

```php
<?php

namespace App\Core\Datastores\PostTag\Interfaces;

use PHPNomad\Datastore\Interfaces\Datastore;

interface PostTagDatastore extends Datastore
{
    /**
     * Get all tags for a post
     */
    public function getTagsForPost(int $postId): iterable;
    
    /**
     * Get all posts for a tag
     */
    public function getPostsForTag(int $tagId): iterable;
    
    /**
     * Add a tag to a post
     */
    public function addTagToPost(int $postId, int $tagId): void;
    
    /**
     * Remove a tag from a post
     */
    public function removeTagFromPost(int $postId, int $tagId): void;
    
    /**
     * Check if a post has a specific tag
     */
    public function postHasTag(int $postId, int $tagId): bool;
}
```

### Implementation

```php
<?php

namespace App\Core\Datastores\PostTag;

use PHPNomad\Datastore\Traits\WithDatastoreDecorator;
use App\Core\Datastores\PostTag\Interfaces\PostTagDatastore as IPostTagDatastore;
use App\Core\Datastores\PostTag\Interfaces\PostTagDatastoreHandler;
use App\Core\Models\PostTag;

class PostTagDatastore implements IPostTagDatastore
{
    use WithDatastoreDecorator;

    public function __construct(
        private PostTagDatastoreHandler $handler
    ) {}

    public function getTagsForPost(int $postId): iterable
    {
        return $this->handler->get(['post_id' => $postId]);
    }

    public function getPostsForTag(int $tagId): iterable
    {
        return $this->handler->get(['tag_id' => $tagId]);
    }

    public function addTagToPost(int $postId, int $tagId): void
    {
        $association = new PostTag($postId, $tagId);
        $this->handler->save($association);
    }

    public function removeTagFromPost(int $postId, int $tagId): void
    {
        $association = new PostTag($postId, $tagId);
        $this->handler->delete($association);
    }

    public function postHasTag(int $postId, int $tagId): bool
    {
        $results = $this->handler->get([
            'post_id' => $postId,
            'tag_id' => $tagId,
        ]);
        
        return !empty($results);
    }
}
```

---

## Working with Junction Tables

### Adding Associations

```php
// Add multiple tags to a post
$postTags = $container->get(PostTagDatastore::class);

$tagIds = [1, 5, 12, 23];
foreach ($tagIds as $tagId) {
    $postTags->addTagToPost(postId: 42, tagId: $tagId);
}
```

### Querying Associations

```php
// Get all tags for a post
$associations = $postTags->getTagsForPost(42);

foreach ($associations as $association) {
    echo "Post 42 has tag {$association->tagId}\n";
}

// Get all posts for a tag
$associations = $postTags->getPostsForTag(5);

foreach ($associations as $association) {
    echo "Tag 5 is on post {$association->postId}\n";
}
```

### Removing Associations

```php
// Remove a specific tag from a post
$postTags->removeTagFromPost(postId: 42, tagId: 5);

// Remove all tags from a post
$associations = $postTags->getTagsForPost(42);
foreach ($associations as $association) {
    $postTags->delete($association);
}
```

### Checking Association Existence

```php
if ($postTags->postHasTag(postId: 42, tagId: 5)) {
    echo "Post 42 has tag 5";
}
```

---

## Loading Related Data

Junction tables are often used with joins to load related entities:

### Service Layer Pattern

```php
<?php

namespace App\Services;

class PostService
{
    public function __construct(
        private PostDatastore $posts,
        private PostTagDatastore $postTags,
        private TagDatastore $tags
    ) {}

    /**
     * Get a post with all its tags loaded
     */
    public function getPostWithTags(int $postId): array
    {
        $post = $this->posts->find($postId);
        
        // Get tag associations for this post
        $associations = $this->postTags->getTagsForPost($postId);
        
        // Load actual tag models
        $tags = [];
        foreach ($associations as $association) {
            $tags[] = $this->tags->find($association->tagId);
        }
        
        return [
            'post' => $post,
            'tags' => $tags,
        ];
    }

    /**
     * Get all posts for a tag
     */
    public function getPostsForTag(int $tagId): array
    {
        $tag = $this->tags->find($tagId);
        
        // Get post associations for this tag
        $associations = $this->postTags->getPostsForTag($tagId);
        
        // Load actual post models
        $posts = [];
        foreach ($associations as $association) {
            $posts[] = $this->posts->find($association->postId);
        }
        
        return [
            'tag' => $tag,
            'posts' => $posts,
        ];
    }
}
```

---

## Junction Tables with Additional Data

Sometimes you need to store **metadata** about the relationship itself. For example, storing when a tag was added to a post, or who added it.

### Extended Junction Table

```php
class PostTagsTable extends Table
{
    public function getColumns(): array
    {
        return [
            new Column('post_id', 'BIGINT', null, 'NOT NULL'),
            new Column('tag_id', 'BIGINT', null, 'NOT NULL'),
            
            // Additional metadata
            new Column('added_by_user_id', 'BIGINT', null, 'NULL'),
            (new DateCreatedFactory())->toColumn(),
        ];
    }

    public function getIndices(): array
    {
        return [
            new Index(['post_id', 'tag_id'], 'primary', 'PRIMARY KEY'),
            new Index(['post_id'], 'post_idx', 'INDEX'),
            new Index(['tag_id'], 'tag_idx', 'INDEX'),
            new Index(['added_by_user_id'], 'user_idx', 'INDEX'),
        ];
    }
}
```

### Extended Model

```php
class PostTag implements DataModel
{
    public function __construct(
        public readonly int $postId,
        public readonly int $tagId,
        public readonly ?int $addedByUserId = null,
        public readonly ?\DateTime $createdAt = null
    ) {}
}
```

---

## Multiple Junction Tables

Complex systems often have multiple many-to-many relationships:

**Example: E-commerce system**

```php
// Products and categories
class ProductCategoriesTable extends Table { ... }

// Orders and products (with quantity, price at time of order)
class OrderProductsTable extends Table
{
    public function getColumns(): array
    {
        return [
            new Column('order_id', 'BIGINT', null, 'NOT NULL'),
            new Column('product_id', 'BIGINT', null, 'NOT NULL'),
            new Column('quantity', 'INT', null, 'NOT NULL'),
            new Column('price_at_purchase', 'DECIMAL', [10, 2], 'NOT NULL'),
        ];
    }
}

// Users and roles
class UserRolesTable extends Table { ... }
```

---

## Best Practices

### Always Use Compound Primary Keys

```php
// ✅ GOOD: compound primary key prevents duplicates
new Index(['post_id', 'tag_id'], 'primary', 'PRIMARY KEY')

// ❌ BAD: allows duplicate associations
new Column('id', 'INT', null, 'NOT NULL AUTO_INCREMENT PRIMARY KEY')
new Column('post_id', 'BIGINT', null, 'NOT NULL')
new Column('tag_id', 'BIGINT', null, 'NOT NULL')
```

### Index Both Directions

```php
// ✅ GOOD: supports queries in both directions
new Index(['post_id'], 'post_idx', 'INDEX'),  // "tags for post"
new Index(['tag_id'], 'tag_idx', 'INDEX'),    // "posts for tag"

// ❌ BAD: only one direction is fast
new Index(['post_id'], 'post_idx', 'INDEX'),
```

### Name Consistently

```php
// ✅ GOOD: consistent naming
post_tags table, post_id and tag_id columns

// ❌ BAD: inconsistent
posts_to_tags table, postId and tagId columns
```

### Keep Models Simple

```php
// ✅ GOOD: minimal junction model
class PostTag
{
    public function __construct(
        public readonly int $postId,
        public readonly int $tagId
    ) {}
}

// ❌ BAD: junction model with business logic
class PostTag
{
    public function isActive(): bool { ... }
    public function validate(): void { ... }
}
```

### Use Batch Operations

```php
// ✅ GOOD: batch insert
foreach ($tagIds as $tagId) {
    $postTags->addTagToPost($postId, $tagId);
}

// ✅ EVEN BETTER: if your datastore supports bulk operations
$postTags->bulkAddTags($postId, $tagIds);
```

---

## What's Next

* [Table Schema Definition](/packages/database/table-schema-definition) — detailed table definition reference
* [Database Handlers](/packages/database/handlers/introduction) — implementing handlers for junction tables
* [Model Adapters](/packages/datastore/model-adapters) — creating adapters for junction models
