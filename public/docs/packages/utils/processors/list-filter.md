# List Filter

A list filter makes it possible to filter items from an array of objects using a chain-able query syntax. This feature
is built-into [Object Registries](/reference/registries/object-registries) via the `ObjectRegistry::query` method,
however it can also be used on raw arrays, as long as each item in the array has the same getters and setters you're
filtering by. This can be done using fully-qualified objects, or by simply converting arrays to objects, as shown below.

```php
use \PHPNomad\Helpers\Processors\ListFilter;

$iceCreamOrderItems = [
  'item_1' => (object) [
      'customer' => (object)['name' => 'Alex', 'id' => 1],
      'scoops' => ['strawberry','chocolate'],
      'cone' => 'waffle',
      'price' => 629,
      'toppings' => ['cheese-crackers']
  ],
  
  'item_2' => (object) [
      'customer' => (object)['name' => 'Devin', 'id' => 2],
      'scoops' => ['chocolate'],
      'cone' => 'chocolateWaffle',
      'price' => 429,
      'toppings' => ['sprinkles']
  ],
  
  'item_3' => (object) [
      'customer' => (object)['name' => 'Kate', 'id' => 3],
      'scoops' => ['vanilla'],
      'cone' => 'basic',
      'price' => 429,
      'toppings' => []
  ],
  
  'item_4' => (object) [
      'customer' => (object)['name' => 'Ben', 'id' => 4],
      'scoops' => ['strawberry','chocolate', 'vanilla'], 
      'cone' => 'bowl',
      'price' => 899,
      'toppings' => ['sprinkles']
  ],
];
```

## Action

An action applies the set of operations against the array, and provides a result in different ways based on the type of
action made.

### Filter Action

The filter action will return a filtered array of items, filtering the results based on the provided criteria in the
operations chained before the call.

```php
// [1,2]
$filtered = (new ListFilter($iceCreamOrderItems))->lessThan('price', 600)->filter();
```

### Find Action

The find action will return the first item found based on the provided criteria in the operations chained before the
call.

```php
// Returns item 1 in the array above
$filtered = (new ListFilter($iceCreamOrderItems))->equals('customer.id', 2)->find();
```

## Operations

An operation is a single specification on how to filter the items in the array. Operations can be chained together, and
will set multiple operations against the filter.

Operations are not applied until either `filter` or `find` is called, and the operations run in the order they're
declared.

```php
// [1]
$filtered = (new ListFilter($iceCreamOrderItems))
  ->greaterThan('price',629)
  ->lessThan('price', 899)
  ->in('toppings','sprinkles')
  ->filter();
```

### Numeric Operations

It's possible to filter numbers based on their value using `lessThan`, `greaterThan`, `lessThanOrEqual`,
and `greaterThanOrEqual`.

```php
// [1,2]
$filtered = (new ListFilter($iceCreamOrderItems))->lessThan('price', 600)->filter();
// [1,2,3]
$filtered = (new ListFilter($iceCreamOrderItems))->greaterThan('price', 400)->filter();
// [1,2,3]
$filtered = (new ListFilter($iceCreamOrderItems))->greaterThanOrEqual('price', 429)->filter();
// [0,1,2]
$filtered = (new ListFilter($iceCreamOrderItems))->lessThanOrEqual('price', 429)->filter();
```

### Instance Operations

It's possible to filter values based on their instance type. Naturally, this requires that the items in-question are an
actual instance. These filters work with the class, as well as any class that they inherit.

```php
interface Content{}

interface Article{}

class BlogPost implements Content, Article{
  /*..*/
}

class MicroPost implements Content, Article{
  /*..*/
}

class Comment implements Content{
  /*..*/
}

$posts = [new BlogPost(),new BlogPost(), new MicroPost(), new Comment()];

// [0,1]
$filtered = (new \PHPNomad\Helpers\Processors\ListFilter($posts))->instanceOf(BlogPost::class);
// [0,1,2]
$filtered = (new \PHPNomad\Helpers\Processors\ListFilter($posts))->instanceOf(Article::class);
// [0,1,3]
$filtered = (new \PHPNomad\Helpers\Processors\ListFilter($posts))->notInstanceOf(MicroPost::class); 
// [3]
$filtered = (new \PHPNomad\Helpers\Processors\ListFilter($posts))->notInstanceOf(Article::class); 
// [0,1,2]
$filtered = (new \PHPNomad\Helpers\Processors\ListFilter($posts))->hasAllInstances(Content::class, Article::class);
// [2,3]
$filtered = (new \PHPNomad\Helpers\Processors\ListFilter($posts))->hasAnyInstances(MicroPost::class, Comment::class);   
```

### Key Operations

These filters work against the array key instead of the array value.

```php
// [1,2]
$filtered = (new ListFilter($iceCreamOrderItems))->keyIn('item_2', 'item_3')->filter();
// [0,3]
$filtered = (new ListFilter($iceCreamOrderItems))->keyNotIn('item_2', 'item_3')->filter();
```

### Value Operations

Value operations work directly against the various property values on the objects inside the array. In order for any
value operation to work, the property must either be `public` (`readonly` is okay!), or has an associated getter method
called `get_${property}` where `${property}` is the name of the property that must be fetched. The getter method takes
priority over the property value, so if you have a getter and a public property, it will use the getter method.

All value operations support dot notation for fetching values nested in objects, and can work with both arrays of
values, and single values.

#### In, Not-In

`in` will set the query to filter out items whose field any of the provided values. `notIn` does the exact opposite.

```php
// [0,2]
$filtered = (new ListFilter($iceCreamOrderItems))->in('cone', 'basic','waffle')->filter();
// [1,3]
$filtered = (new ListFilter($iceCreamOrderItems))->in('toppings', 'sprinkles')->filter();
// [0,1,3]
$filtered = (new ListFilter($iceCreamOrderItems))->in('toppings', 'sprinkles', 'cheese-crackers')->filter();
// [0,2]
$filtered = (new ListFilter($iceCreamOrderItems))->notIn('toppings', 'sprinkles')->filter();
// [2]
$filtered = (new ListFilter($iceCreamOrderItems))->notIn('toppings', 'sprinkles', 'cheese-crackers')->filter();
// [3]
$filtered = (new ListFilter($iceCreamOrderItems))->in('customer.name','Ben')->filter();
```

#### And

Sets the query to filter out items whose field has all the provided values.

```php
// [0,3]
$filtered = (new ListFilter($iceCreamOrderItems))->and('scoops', 'strawberry','chocolate')->filter();
```

#### Equals

Sets the query to filter out items whose value is not identical to the provided value.

```php
// [1,2]
$filtered = (new ListFilter($iceCreamOrderItems))->and('price', 429)->filter();
// [0]
$filtered = (new ListFilter($iceCreamOrderItems))->and('scoops', ['strawberry','chocolate'])->filter();
```

### Callback

If all-else fails, you can chain in a callback to filter items. The example below would filter out any item whose cone
type does not begin with the letter 'b':

```php
// [2,3]
$filtered = (new ListFilter($iceCreamOrderItems))
    ->filterFromCallback('cone', fn(string $cone) => 0 === strpos($cone,'b'))
    ->filter();
```

## Seeding

The concrete `ListFilter` class can be seeded directly, using an array. This can be done both with the enums, and
without. The enum is the preferred method, but if you're confident that the input is accurate, you can technically use
an array directly. This can be useful in scenarios such as directly querying a registry using a REST endpoint, or
something like that.

```php
use PHPNomad\Enums\Filter;

// Using Enums
ListFilter::seed($iceCreamOrderItems, [
    Filter::in->field('cone')            => ['waffle', 'chocolateWaffle'],
    Filter::greaterThan->field('price') => 429,
])->filter()

// Raw query
ListFilter::seed($iceCreamOrderItems, [
    'cone_In'            => ['waffle', 'chocolateWaffle'],
    'price_GreaterThan' => 429,
])->filter()
```