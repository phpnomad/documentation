# Array Helper

The `ArrayHelper` class contains several helper methods that make working with native PHP arrays a little easier. These methods are all statically accessible, and can be chained together using the [Array Processor](./processors/array-processor).

## Process

Instantiates an [Array Processor](./processors/array-processor), which allows an array to have any method in `ArrayHelper` used in a chain.

```php
// Outputs "1, 3, bar, baz, foo"
echo ArrayHelper::process(['foo', 'bar', 'baz', null, 1, 3])
                 ->whereNotNull()
                 ->sort(fn ($a, $b) => $a <=> $b)
                 ->setSeparator(', ');
```

## Where Not Null

Helper method to filter out values that are `null`

```php
//Returns ['foo','bar','baz']
ArrayHelper::whereNotNull(['foo','bar','baz',null]);
```

## Each

Calls the specified callback on each item in a foreach loop. If the array is associative, the key is retained. Functional methods like this are particularly useful because they can require type safety in your callbacks.

The example below converts an array of tag names keyed by their URL-friendly slug into hashtags.

```php
//Returns ['outer-banks' => '#OuterBanks', 'north-carolina' => '#NorthCarolina', 'travel' => '#Travel']
$hashtags = ArrayHelper::each([
  'outer-banks'    => 'Outer Banks',
  'north-carolina' => 'North Carolina',
  'travel'         => 'Travel',
], fn(string $value, string $key) => '#' . StringHelper::pascalCase($value));
```

## After

Fetches items after the specified array position.

```php
use PHPNomad\Helpers\ArrayHelper;

//['bar','baz']
ArrayHelper::after(['foo','bar','baz'],1);
```

## Before

The opposite of `ArrayHelper::after`. Fetches items Before_ the specified array position.

```php
use PHPNomad\Helpers\ArrayHelper;

//['foo']
ArrayHelper::before(['foo','bar','baz'],1);
```

## Dot

Fetches an item from an array using a dot notation. Throws an `ItemNotFound` if the item provided could not be located in the array.

```php
use PHPNomad\Helpers\ArrayHelper;

try{
  // baz
  ArrayHelper::dot(['foo' => ['bar' => 'baz']], 'foo.bar')
}catch(ItemNotFound $e){
  // Handle cases where the item was not found.
}
```

## Remove

Removes an item from the array, and returns the transformed array.

```php
// ['peanut butter' => 'JIF', 'jelly' => 'Smucker\'s']
ArrayHelper::remove(['milk' => 'Goshen Dairy','peanut butter' => 'JIF', 'jelly' => 'Smucker\'s'], 'milk');
```

## Wrap

Forces an item to be an array, even if it isn't an array.

```php
// [123]
ArrayHelper::wrap(123);
```

## Hydrate

Creates an array of new instances given the arguments to pass into the instance constructor.

```php

class Tag{

	public function _Construct(public readonly string $slug, public readonly string $name){

	}
}

// [(Tag),(Tag),(Tag)
ArrayHelper::hydrate([
		['rv-life', 'RVLife'],
		['travel','Travel'],
		['wordpress','WordPress']
	],Tag::class)
```

## Flatten

Flatten arrays of arrays into a single array where the parent array is embedded as an item keyed by the `$key`.

```php
/**
 *  [
 *    ['id' => 'group-1', 'key' => 'value', 'another' => 'value'],
 *    ['id' => 'group-1', 'key' => 'another-value', 'another' => 'value'],
 *    ['id' => 'group-2', 'key' => 'value', 'another' => 'value'],
 *    ['id' => 'group-2', 'key' => 'another-value', 'another' => 'value']
 *  ]
 */
ArrayHelper::flatten([
  'group-1' => [['key' => 'value', 'another' => 'value'], ['key' => 'another-value', 'another' => 'value']],
  'group-2' => [['key' => 'value', 'another' => 'value'], ['key' => 'another-value', 'another' => 'value']],
], 'id')
```

## To Indexed

Updates the array to contain a key equal to the array's key value.

```php
/**
 * [
 *   ['slug' => 'travel','name' => 'Travel'],
 *   ['slug' => 'rv-life','name' => 'RV Life'],
 *   ['slug' => 'wordpress','name' => 'WordPress']
 * ]
 */
ArrayHelper::toIndexed(['travel' => 'Travel','rv-life' => 'RVLife','wordpress' => 'Wordpress'], 'slug', 'name');
```

## Sort

Sorts array using the specified subject, sorting method, and direction. Transforms the array directly.

```php
$items = ['bar','foo','baz'];

// ['foo', 'baz', 'bar']
ArrayHelper::sort($items,SORTREGULAR,Direction::Descending);
```

This also supports providing a callback for the sort, instead:

```php
class Tag{

	public function _Construct(public readonly string $slug, public readonly string $name){

	}
}

$items = [
  new Tag('rv-life','RV Life'),
  new Tag('travel','Travel'),
  new Tag('outer-banks', 'Outer Banks'),
  new Tag('taos', 'Taos')
];

// [Tag(outer-banks), Tag(rv-life), Tag(taos), Tag(travel)]
ArrayHelper::sort($items,fn(Tag $a, Tag $b) => $a->slug <=> $b->slug);
```

## Pluck

Plucks a single item from an array, given a key. Falls back to a default value if it is not set.

```php
// 'bar'
ArrayHelper::pluck(['foo' => 'bar'],'foo','baz');

// 'baz'
ArrayHelper::pluck(['foo' => 'bar'],'invalid','baz');
```

If the item is not an array, it will also provide the default value.

```php
// 'baz'
ArrayHelper::pluck('This is clearly not an array...and yet.','invalid','baz');
```

## Pluck Recursive

Plucks a specific value from an array of items.

```php
$items = [
    ['slug' => 'rv-life',  'name' => 'RVLife'],
    ['slug' => 'travel', 'name' => 'Travel'],
    ['slug' => 'wordpress', 'name' => 'WordPress'],
    ['name' => 'Invalid']
];

// ['rv-life','travel','outer-banks','taos', null]
ArrayHelper::PluckRecursive($items,'slug', null);
```

This also works with objects:

```php
class Tag{

	public function _Construct(public readonly string $slug, public readonly string $name){

	}
}

$items = [
  new Tag('rv-life','RV Life'),
  new Tag('travel','Travel'),
  new Tag('outer-banks', 'Outer Banks'),
  new Tag('taos', 'Taos')
];

// ['rv-life','travel','outer-banks','taos']
ArrayHelper::pluckRecursive($items, 'slug', null);
```

## Cast

Cast all items in the array to the specified type.

```php
// [1, 234,12,123,0,0]
ArrayHelper::cast(['1','234','12.34',123,'alex',false], 'int');
```

## Append

Adds the specified item(s) to the end of an array.

```php
// ['foo','bar','baz']
ArrayHelper::append(['foo'],'bar','baz');
```

## Is Associative

Returns true if this array is an associative array.

```php
// true
ArrayHelper::isAssociative(['foo' => 'bar']);

// false
ArrayHelper::isAssociative(['foo', 'bar', 'baz']);
```

## Normalize

Recursively sorts, and optionally mutates an array of arrays. Useful when preparing for caching purposes because it ensures that any array that is technically identical, although in a different order, is the same. This can also convert a closure into a format that can be safely converted into a hash.

Generally, this is used to prepare an array to be converted into a consistent hash, regardless of what order the items in the array are stored.

```php
$cachedQuery = [
  'postType'      => 'post',
  'postsPerPage' => -1,
  'metaQuery'     => [
    'relation' => 'OR',
    [
      'key'     => 'likes',
      'value'   => 50,
      'compare' => '>',
      'type'    => 'numeric',
    ],
  ],
];

/**
* [
*   'metaQuery' => [
*      'relation' => 'OR'
*      [
*        'compare' => '>'
*        'key' => 'likes'
*        'type' => 'numeric'
*        'value' => 50
*      ]
*  ]
*   'postType' => 'post'
*   'postsPerPage' => int -1
 * ]
 */
ArrayHelper::normalize($cachedQuery)
```

## Proxies

There are also several methods that serve as direct proxies for `array_*` functions, with the only difference being that the order of the arguments always put the input array as the first argument (haystack comes first).

* map => arrayMap
* reduce => arrayReduce
* filter => arrayFilter
* values => arrayValues
* keys => arrayKeys
* unique => arrayUnique
* keySort => ksort
* merge => arrayMerge
* reverse => arrayReverse
* prepend => arrayUnshift
* intersect => arrayIntersect
* intersectKeys => arrayIntersectKeys
* diff => arrayDiff
* replaceRecursive => arrayReplaceRecursive
* replace => arrayReplace