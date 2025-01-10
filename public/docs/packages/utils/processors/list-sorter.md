# List Sorter

A list sorter makes it possible to sort items from an array of objects using a chain-able query syntax. This feature
is built-into [Object Registries](/reference/registries/object-registries) via the `ObjectRegistry::query` method,
however it can also be used on raw arrays, as long as each item in the array has the same getters and setters you're
sorting by. This can be done using fully-qualified objects, or by simply converting arrays to objects, as shown below.

```php
use \PHPNomad\Helpers\Processors\ListFilter;

$iceCreamOrderItems = [
  (object) [
      'customer' => (object)['name' => 'Alex', 'id' => 1],
      'scoops' => ['strawberry','chocolate'],
      'cone' => 'waffle',
      'price' => 629,
      'toppings' => ['cheese-crackers']
  ],
  
  (object) [
      'customer' => (object)['name' => 'Devin', 'id' => 2],
      'scoops' => ['chocolate'],
      'cone' => 'chocolateWaffle',
      'price' => 429,
      'toppings' => ['sprinkles']
  ],
  
  (object) [
      'customer' => (object)['name' => 'Kate', 'id' => 3],
      'scoops' => ['vanilla'],
      'cone' => 'basic',
      'price' => 429,
      'toppings' => []
  ],
  
  (object) [
      'customer' => (object)['name' => 'Ben', 'id' => 4],
      'scoops' => ['strawberry','chocolate', 'vanilla'], 
      'cone' => 'bowl',
      'price' => 899,
      'toppings' => ['sprinkles']
  ],
];
```

## Usage

Sort items by price
```php
$sorted = (new ListSorter($iceCreamOrderItems))->sortBy('price')->sort()
$sortedReverse = (new ListSorter($iceCreamOrderItems))->sortBy('price', Direction::Descending)->sort()
```

Nested object values are supported. Sort items by customer name.
```php
$sorted = (new ListSorter($iceCreamOrderItems))->sortBy('customer.name')->sort()
$sortedReverse = (new ListSorter($iceCreamOrderItems))->sortBy('customer.name', Direction::Descending)->sort()
```

## Custom Sorting Method

The default sorting method provided in `ListSorter` is sufficient for most cases, however, if you need to create a custom sorting algorithm for it, this can be done by extending the `SortMethod` class.

The example below creates a custom sorting method that makes it possible to sort items based on the number of items in the array. This allows us to sort the ice cream orders by the number of scoops.

```php
use PHPNomad\Abstracts\SortMethod;
use \PHPNomad\Helpers\ObjectHelper;

// First create the sorter.
class ArrayCountSorter extends SortMethod{

  // The sort method is called on each item, and works much like usort, except it also includes the field name and the direction.
  public function sort( object $a, object $b, string $field, Direction $direction ): int
  {
    // The spaceship operator will return -1, 0, or 1 based on the result. See PHP docs.
    $result = count(ObjectHelper::pluck($a, $field)) <=> count(ObjectHelper::pluck($b, $field));

    // Invert the result if it's descending, otherwise simply return the result as-is.
    return $direction === Direction::Descending ? $result * -1 : $result;
  }
}

// Use the custom sorter method with scoops.
$sorted = (new ListSorter($iceCreamOrderItems))
    ->sortBy(field: 'scoops', method: ArrayCountSorter::class)
    ->sort();

// Use the custom sorter method with scoops, only this time reverse it, Missy Elliott style.
$sortedReverse = (new ListSorter($iceCreamOrderItems))
    ->sortBy(field: 'scoops', direction: Direction::Descending, method: ArrayCountSorter::class)
    ->sort();
```