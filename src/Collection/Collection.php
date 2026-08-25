<?php

declare(strict_types=1);

namespace Switch\Foundation\Collection;

use ArrayIterator;
use Closure;
use InvalidArgumentException;
use OutOfBoundsException;
use RuntimeException;
use Traversable;

/**
 * Modern, High-Velocity, Feature-Rich Collection Engine for Switch Framework.
 *
 * @template TKey of array-key
 * @template TValue
 * @implements Enumerable<TKey, TValue>
 */
class Collection implements Enumerable
{
    /**
     * The items contained in the collection.
     *
     * @var array<TKey, TValue>
     */
    protected array $items = [];

    /**
     * Create a new collection instance.
     *
     * @param mixed $items
     */
    public function __construct(mixed $items = [])
    {
        $this->items = $this->getArrayableItems($items);
    }

    /**
     * Create a new collection instance if the value isn't one already.
     */
    public static function make(mixed $items = []): static
    {
        return new static($items);
    }

    /**
     * Wrap the given value in a collection if it is not already.
     */
    public static function wrap(mixed $value): static
    {
        if ($value instanceof static) {
            return $value;
        }

        if ($value === null) {
            return new static([]);
        }

        return new static(is_array($value) ? $value : [$value]);
    }

    /**
     * Create an empty collection.
     */
    public static function empty(): static
    {
        return new static([]);
    }

    /**
     * Create a new collection by invoking the callback a given number of times.
     */
    public static function times(int $number, ?callable $callback = null): static
    {
        if ($number < 1) {
            return new static([]);
        }

        if ($callback === null) {
            return new static(range(1, $number));
        }

        $result = [];
        for ($i = 1; $i <= $number; $i++) {
            $result[] = $callback($i);
        }

        return new static($result);
    }

    /**
     * Create a collection with a range of numbers.
     */
    public static function range(int|float $from, int|float $to, int|float $step = 1): static
    {
        return new static(range($from, $to, $step));
    }

    /**
     * Convert the eager collection into a generator-backed LazyCollection.
     */
    public function lazy(): LazyCollection
    {
        return new LazyCollection(function () {
            foreach ($this->items as $key => $value) {
                yield $key => $value;
            }
        });
    }

    /**
     * Get all items in the collection.
     *
     * @return array<TKey, TValue>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Get the average value of a given key or items.
     */
    public function avg(?string $key = null): float|int|null
    {
        $count = $this->count();
        if ($count === 0) {
            return null;
        }

        return $this->sum($key) / $count;
    }

    public function average(?string $key = null): float|int|null
    {
        return $this->avg($key);
    }

    /**
     * Calculate the median of a given key or values.
     */
    public function median(?string $key = null): float|int|null
    {
        $values = ($key ? $this->pluck($key) : $this)->filter(fn($v) => $v !== null)->sort()->values()->all();
        $count = count($values);

        if ($count === 0) {
            return null;
        }

        $middle = (int) ($count / 2);

        if ($count % 2 === 1) {
            return $values[$middle];
        }

        return ($values[$middle - 1] + $values[$middle]) / 2;
    }

    /**
     * Calculate the mode of a given key or values.
     *
     * @return array<int, mixed>|null
     */
    public function mode(?string $key = null): ?array
    {
        if ($this->isEmpty()) {
            return null;
        }

        $values = ($key ? $this->pluck($key) : $this)->filter(fn($v) => $v !== null)->all();
        $counts = array_count_values(array_map('strval', $values));
        arsort($counts);

        $max = reset($counts);
        $modes = [];

        foreach ($counts as $val => $frequency) {
            if ($frequency === $max) {
                $modes[] = is_numeric($val) ? (str_contains($val, '.') ? (float) $val : (int) $val) : $val;
            }
        }

        return $modes;
    }

    /**
     * Chunk the collection into smaller collections of a given size.
     */
    public function chunk(int $size): static
    {
        if ($size <= 0) {
            return new static([]);
        }

        $chunks = [];
        foreach (array_chunk($this->items, $size, true) as $chunk) {
            $chunks[] = new static($chunk);
        }

        return new static($chunks);
    }

    /**
     * Chunk items into collections while callback returns true.
     */
    public function chunkWhile(callable $callback): static
    {
        if ($this->isEmpty()) {
            return new static([]);
        }

        $chunks = new static([]);
        $chunk = new static([$this->first()]);

        $this->slice(1)->each(function ($item, $key) use (&$chunks, &$chunk, $callback) {
            if ($callback($item, $key, $chunk)) {
                $chunk->push($item);
            } else {
                $chunks->push($chunk);
                $chunk = new static([$item]);
            }
        });

        $chunks->push($chunk);
        return $chunks;
    }

    /**
     * Collapse a collection of arrays into a single flat collection.
     */
    public function collapse(): static
    {
        $results = [];
        foreach ($this->items as $values) {
            if ($values instanceof static) {
                $values = $values->all();
            } elseif (!is_array($values)) {
                continue;
            }
            $results = array_merge($results, $values);
        }

        return new static($results);
    }

    /**
     * Determine if an item or key/value pair exists in the collection.
     */
    public function contains(mixed $key, mixed $value = null): bool
    {
        if (func_num_args() === 1) {
            if (is_callable($key)) {
                return $this->first($key) !== null;
            }
            return in_array($key, $this->items, false);
        }

        return $this->first(fn($item) => $this->dataGet($item, $key) == $value) !== null;
    }

    /**
     * Determine if an item or key/value pair exists strictly.
     */
    public function containsStrict(mixed $key, mixed $value = null): bool
    {
        if (func_num_args() === 1) {
            if (is_callable($key)) {
                return $this->first($key) !== null;
            }
            return in_array($key, $this->items, true);
        }

        return $this->first(fn($item) => $this->dataGet($item, $key) === $value) !== null;
    }

    public function doesntContain(mixed $key, mixed $value = null): bool
    {
        return !$this->contains(...func_get_args());
    }

    /**
     * Cross join with the given lists, returning all combinations.
     */
    public function crossJoin(...$arrays): static
    {
        $results = [[]];
        $allArrays = array_merge([$this->items], array_map(fn($a) => $this->getArrayableItems($a), $arrays));

        foreach ($allArrays as $items) {
            $append = [];
            foreach ($results as $product) {
                foreach ($items as $item) {
                    $append[] = array_merge($product, [$item]);
                }
            }
            $results = $append;
        }

        return new static($results);
    }

    /**
     * Diff the collection against the given items.
     */
    public function diff(mixed $items): static
    {
        return new static(array_diff($this->items, $this->getArrayableItems($items)));
    }

    public function diffKeys(mixed $items): static
    {
        return new static(array_diff_key($this->items, $this->getArrayableItems($items)));
    }

    public function diffAssoc(mixed $items): static
    {
        return new static(array_diff_assoc($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Retrieve duplicate items from the collection.
     */
    public function duplicates(?string $key = null, bool $strict = false): static
    {
        $items = $key ? $this->pluck($key) : $this;
        $unique = [];
        $duplicates = [];

        foreach ($items as $k => $value) {
            if (in_array($value, $unique, $strict)) {
                $duplicates[$k] = $value;
            } else {
                $unique[] = $value;
            }
        }

        return new static($duplicates);
    }

    /**
     * Execute a callback over each item.
     */
    public function each(callable $callback): static
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * Determine if all items pass the truth test.
     */
    public function every(callable $callback): bool
    {
        foreach ($this->items as $key => $item) {
            if (!$callback($item, $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if at least one item passes the truth test.
     */
    public function some(callable $callback): bool
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Filter items using a callback or truthiness test.
     */
    public function filter(?callable $callback = null): static
    {
        if ($callback) {
            return new static(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
        }

        return new static(array_filter($this->items));
    }

    /**
     * Filter items removing those where callback returns true (inverted filter).
     */
    public function reject(callable $callback): static
    {
        return $this->filter(fn($item, $key) => !$callback($item, $key));
    }

    /**
     * Get the first item passing the truth test, or fallback to default.
     */
    public function first(?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            if (empty($this->items)) {
                return $default instanceof Closure ? $default() : $default;
            }
            return reset($this->items);
        }

        foreach ($this->items as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return $default instanceof Closure ? $default() : $default;
    }

    /**
     * Get the first item or throw an exception if empty.
     */
    public function firstOrFail(?callable $callback = null): mixed
    {
        $first = $this->first($callback);
        if ($first === null) {
            throw new RuntimeException('Item not found in collection.');
        }
        return $first;
    }

    /**
     * Get the first item that matches a key-value condition.
     */
    public function firstWhere(string $key, mixed $operator = null, mixed $value = null): mixed
    {
        return $this->where(...func_get_args())->first();
    }

    /**
     * Get the sole matching element. Throws if 0 or >1 matches.
     */
    public function sole(?callable $callback = null): mixed
    {
        $items = $callback ? $this->filter($callback) : $this;

        $count = $items->count();
        if ($count === 0) {
            throw new RuntimeException('Item not found in collection.');
        }
        if ($count > 1) {
            throw new RuntimeException("Expected 1 item, found {$count}.");
        }

        return $items->first();
    }

    /**
     * Map a collection and flatten the result by a single level.
     */
    public function flatMap(callable $callback): static
    {
        return $this->map($callback)->collapse();
    }

    /**
     * Flatten a multi-dimensional collection.
     */
    public function flatten(?int $depth = null): static
    {
        $depth = $depth ?? PHP_INT_MAX;
        $result = [];

        $flattenFn = function ($items, $currentDepth) use (&$flattenFn, &$result, $depth) {
            foreach ($items as $item) {
                if ($item instanceof static) {
                    $item = $item->all();
                }

                if (is_array($item) && $currentDepth < $depth) {
                    $flattenFn($item, $currentDepth + 1);
                } else {
                    $result[] = $item;
                }
            }
        };

        $flattenFn($this->items, 1);
        return new static($result);
    }

    /**
     * Flip the items in the collection.
     */
    public function flip(): static
    {
        return new static(array_flip($this->items));
    }

    /**
     * Remove one or more items from the collection by key.
     */
    public function forget(array|string|int $keys): static
    {
        foreach ((array) $keys as $key) {
            unset($this->items[$key]);
        }

        return $this;
    }

    /**
     * Get an item from the collection by key with dot notation support.
     */
    public function get(string|int $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }

        return $this->dataGet($this->items, (string) $key, $default);
    }

    /**
     * Group an associative array by a field or using a callback.
     */
    public function groupBy(string|callable $groupBy, bool $preserveKeys = false): static
    {
        $results = [];

        foreach ($this->items as $key => $value) {
            $groupKey = is_callable($groupBy) ? $groupBy($value, $key) : $this->dataGet($value, $groupBy);
            $groupKey = is_bool($groupKey) ? (int) $groupKey : (string) $groupKey;

            if (!isset($results[$groupKey])) {
                $results[$groupKey] = new static([]);
            }

            if ($preserveKeys) {
                $results[$groupKey][$key] = $value;
            } else {
                $results[$groupKey][] = $value;
            }
        }

        return new static($results);
    }

    /**
     * Key an associative array by a field or using a callback.
     */
    public function keyBy(string|callable $keyBy): static
    {
        $results = [];

        foreach ($this->items as $key => $item) {
            $resolvedKey = is_callable($keyBy) ? $keyBy($item, $key) : $this->dataGet($item, $keyBy);
            $results[(string) $resolvedKey] = $item;
        }

        return new static($results);
    }

    /**
     * Determine if one or more keys exist in the collection.
     */
    public function has(array|string|int $key): bool
    {
        $keys = is_array($key) ? $key : func_get_args();
        foreach ($keys as $k) {
            if (!array_key_exists($k, $this->items)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if any of the keys exist in the collection.
     */
    public function hasAny(array|string|int $key): bool
    {
        $keys = is_array($key) ? $key : func_get_args();
        foreach ($keys as $k) {
            if (array_key_exists($k, $this->items)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Intersect the collection with the given items.
     */
    public function intersect(mixed $items): static
    {
        return new static(array_intersect($this->items, $this->getArrayableItems($items)));
    }

    public function intersectKeys(mixed $items): static
    {
        return new static(array_intersect_key($this->items, $this->getArrayableItems($items)));
    }

    public function intersectAssoc(mixed $items): static
    {
        return new static(array_intersect_assoc($this->items, $this->getArrayableItems($items)));
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Join collection items into a string with delimiter and optional final glue.
     */
    public function join(string $glue, string $finalGlue = ''): string
    {
        if ($finalGlue === '') {
            return implode($glue, $this->items);
        }

        $count = $this->count();
        if ($count === 0) {
            return '';
        }
        if ($count === 1) {
            return (string) $this->first();
        }

        $collection = new static($this->items);
        $finalItem = (string) $collection->pop();

        return implode($glue, $collection->all()) . $finalGlue . $finalItem;
    }

    /**
     * Get the keys of the collection items.
     */
    public function keys(): static
    {
        return new static(array_keys($this->items));
    }

    /**
     * Get the last item passing the truth test, or fallback to default.
     */
    public function last(?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            if (empty($this->items)) {
                return $default instanceof Closure ? $default() : $default;
            }
            return end($this->items);
        }

        return (new static(array_reverse($this->items, true)))->first($callback, $default);
    }

    /**
     * Transform each item in the collection using a callback.
     */
    public function map(callable $callback): static
    {
        $keys = array_keys($this->items);
        $items = array_map($callback, $this->items, $keys);

        return new static(array_combine($keys, $items));
    }

    /**
     * Map a collection into instances of a new class.
     */
    public function mapInto(string $class): static
    {
        return $this->map(fn($value, $key) => new $class($value, $key));
    }

    /**
     * Map a collection and associate new keys to the transformed values.
     */
    public function mapWithKeys(callable $callback): static
    {
        $result = [];

        foreach ($this->items as $key => $value) {
            $assoc = $callback($value, $key);
            foreach ($assoc as $mapK => $mapV) {
                $result[$mapK] = $mapV;
            }
        }

        return new static($result);
    }

    /**
     * Map a collection of arrays by spreading arguments into the callback.
     */
    public function mapSpread(callable $callback): static
    {
        return $this->map(function ($chunk, $key) use ($callback) {
            $params = array_values($this->getArrayableItems($chunk));
            $params[] = $key;
            return $callback(...$params);
        });
    }

    /**
     * Get the maximum value of a given key or values.
     */
    public function max(?string $key = null): mixed
    {
        $target = $key ? $this->pluck($key)->filter(fn($v) => $v !== null)->all() : $this->filter(fn($v) => $v !== null)->all();
        return !empty($target) ? max($target) : null;
    }

    /**
     * Get the minimum value of a given key or values.
     */
    public function min(?string $key = null): mixed
    {
        $target = $key ? $this->pluck($key)->filter(fn($v) => $v !== null)->all() : $this->filter(fn($v) => $v !== null)->all();
        return !empty($target) ? min($target) : null;
    }

    /**
     * Merge the collection with the given items.
     */
    public function merge(mixed $items): static
    {
        return new static(array_merge($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Recursively merge the collection with the given items.
     */
    public function mergeRecursive(mixed $items): static
    {
        return new static(array_merge_recursive($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Replace collection items with the given items.
     */
    public function replace(mixed $items): static
    {
        return new static(array_replace($this->items, $this->getArrayableItems($items)));
    }

    public function replaceRecursive(mixed $items): static
    {
        return new static(array_replace_recursive($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Create a new collection consisting of every n-th element.
     */
    public function nth(int $step, int $offset = 0): static
    {
        $new = [];
        $position = 0;

        foreach ($this->items as $item) {
            if ($position % $step === $offset) {
                $new[] = $item;
            }
            $position++;
        }

        return new static($new);
    }

    /**
     * Pad collection to the specified length with a value.
     */
    public function pad(int $size, mixed $value): static
    {
        return new static(array_pad($this->items, $size, $value));
    }

    /**
     * Partition the collection into two collections according to the callback.
     *
     * @return array{0: static, 1: static}
     */
    public function partition(callable $callback): array
    {
        $passed = [];
        $failed = [];

        foreach ($this->items as $key => $item) {
            if ($callback($item, $key)) {
                $passed[$key] = $item;
            } else {
                $failed[$key] = $item;
            }
        }

        return [new static($passed), new static($failed)];
    }

    /**
     * Calculate percentage of items matching the callback.
     */
    public function percentage(callable $callback, ?int $precision = null): ?float
    {
        if ($this->isEmpty()) {
            return null;
        }

        $percentage = ($this->filter($callback)->count() / $this->count()) * 100;
        return $precision !== null ? round($percentage, $precision) : $percentage;
    }

    /**
     * Pipe the collection through the given callback.
     */
    public function pipe(callable $callback): mixed
    {
        return $callback($this);
    }

    /**
     * Pipe the collection into a new class instance.
     */
    public function pipeInto(string $class): mixed
    {
        return new $class($this);
    }

    /**
     * Pipe the collection through an array of pipeline closures.
     */
    public function pipeThrough(array $callbacks): mixed
    {
        return (new static($callbacks))->reduce(
            fn($pipeline, $callback) => $callback($pipeline),
            $this
        );
    }

    /**
     * Fetch values of a given key with deep dot notation support.
     */
    public function pluck(string $value, ?string $key = null): static
    {
        $results = [];

        foreach ($this->items as $item) {
            $itemValue = $this->dataGet($item, $value);

            if ($key === null) {
                $results[] = $itemValue;
            } else {
                $itemKey = $this->dataGet($item, $key);
                $results[(string) $itemKey] = $itemValue;
            }
        }

        return new static($results);
    }

    /**
     * Pop one or more items off the end of the collection.
     */
    public function pop(int $count = 1): mixed
    {
        if ($this->isEmpty()) {
            return null;
        }

        if ($count === 1) {
            return array_pop($this->items);
        }

        $results = [];
        for ($i = 0; $i < $count; $i++) {
            $results[] = array_pop($this->items);
        }

        return new static($results);
    }

    /**
     * Push one or more items onto the end of the collection.
     */
    public function push(mixed ...$values): static
    {
        foreach ($values as $value) {
            $this->items[] = $value;
        }

        return $this;
    }

    /**
     * Prepend one or more items to the beginning of the collection.
     */
    public function prepend(mixed $value, mixed $key = null): static
    {
        if ($key === null) {
            array_unshift($this->items, $value);
        } else {
            $this->items = [$key => $value] + $this->items;
        }

        return $this;
    }

    /**
     * Pull an item from the collection by key, removing it.
     */
    public function pull(string|int $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);
        return $value;
    }

    /**
     * Put an item in the collection by key.
     */
    public function put(string|int $key, mixed $value): static
    {
        $this->items[$key] = $value;
        return $this;
    }

    /**
     * Get one or a specified number of random items.
     */
    public function random(?int $number = null): mixed
    {
        $count = $this->count();
        if ($count === 0) {
            return null;
        }

        if ($number === null) {
            return $this->items[array_rand($this->items)];
        }

        if ($number <= 0 || $number > $count) {
            throw new InvalidArgumentException("Cannot request {$number} items from a collection of {$count}.");
        }

        $keys = (array) array_rand($this->items, $number);
        $results = [];
        foreach ($keys as $k) {
            $results[] = $this->items[$k];
        }

        return new static($results);
    }

    /**
     * Reduce the collection to a single value.
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        $result = $initial;
        foreach ($this->items as $key => $value) {
            $result = $callback($result, $value, $key);
        }

        return $result;
    }

    /**
     * Reverse items order in collection.
     */
    public function reverse(bool $preserveKeys = true): static
    {
        return new static(array_reverse($this->items, $preserveKeys));
    }

    /**
     * Search the collection for a given value and return corresponding key.
     */
    public function search(mixed $value, bool $strict = false): int|string|false
    {
        if (is_callable($value)) {
            foreach ($this->items as $key => $item) {
                if ($value($item, $key)) {
                    return $key;
                }
            }
            return false;
        }

        return array_search($value, $this->items, $strict);
    }

    /**
     * Shift an item off the beginning of the collection.
     */
    public function shift(int $count = 1): mixed
    {
        if ($this->isEmpty()) {
            return null;
        }

        if ($count === 1) {
            return array_shift($this->items);
        }

        $results = [];
        for ($i = 0; $i < $count; $i++) {
            $results[] = array_shift($this->items);
        }

        return new static($results);
    }

    /**
     * Shuffle the items in the collection.
     */
    public function shuffle(?int $seed = null): static
    {
        $items = $this->items;
        if ($seed !== null) {
            mt_srand($seed);
        }
        shuffle($items);
        if ($seed !== null) {
            mt_srand();
        }

        return new static($items);
    }

    /**
     * Slice the underlying collection array.
     */
    public function slice(int $offset, ?int $length = null): static
    {
        return new static(array_slice($this->items, $offset, $length, true));
    }

    /**
     * Splice a portion of the underlying collection array.
     */
    public function splice(int $offset, ?int $length = null, mixed $replacement = []): static
    {
        if (func_num_args() === 1) {
            return new static(array_splice($this->items, $offset));
        }

        return new static(array_splice($this->items, $offset, $length, $this->getArrayableItems($replacement)));
    }

    /**
     * Split a collection into a certain number of groups.
     */
    public function split(int $numberOfGroups): static
    {
        if ($this->isEmpty() || $numberOfGroups <= 0) {
            return new static([]);
        }

        $groupSize = (int) ceil($this->count() / $numberOfGroups);
        return $this->chunk($groupSize);
    }

    /**
     * Create sliding windows of a specified size.
     */
    public function sliding(int $size = 2, int $step = 1): static
    {
        $chunks = [];
        $count = $this->count();

        if ($size <= 0 || $count < $size) {
            return new static([]);
        }

        for ($i = 0; $i <= $count - $size; $i += $step) {
            $chunks[] = new static(array_slice($this->items, $i, $size));
        }

        return new static($chunks);
    }

    /**
     * Sort through each item with a callback.
     */
    public function sort(?callable $callback = null): static
    {
        $items = $this->items;

        $callback
            ? uasort($items, $callback)
            : asort($items);

        return new static($items);
    }

    /**
     * Sort the collection using the given callback or key.
     */
    public function sortBy(string|callable $callback, int $options = SORT_REGULAR, bool $descending = false): static
    {
        $results = [];
        $callback = is_callable($callback) ? $callback : fn($item) => $this->dataGet($item, $callback);

        foreach ($this->items as $key => $value) {
            $results[$key] = $callback($value, $key);
        }

        $descending ? arsort($results, $options) : asort($results, $options);

        foreach (array_keys($results) as $key) {
            $results[$key] = $this->items[$key];
        }

        return new static($results);
    }

    public function sortByDesc(string|callable $callback, int $options = SORT_REGULAR): static
    {
        return $this->sortBy($callback, $options, true);
    }

    public function sortKeys(int $options = SORT_REGULAR, bool $descending = false): static
    {
        $items = $this->items;
        $descending ? krsort($items, $options) : ksort($items, $options);
        return new static($items);
    }

    public function sortKeysDesc(int $options = SORT_REGULAR): static
    {
        return $this->sortKeys($options, true);
    }

    /**
     * Sum the values in collection.
     */
    public function sum(?string $key = null): float|int
    {
        if ($key === null) {
            return array_sum($this->items);
        }

        $total = 0;
        foreach ($this->items as $item) {
            $total += (float) $this->dataGet($item, $key, 0);
        }

        return $total;
    }

    /**
     * Take the first or last {$limit} items.
     */
    public function take(int $limit): static
    {
        if ($limit < 0) {
            return $this->slice($limit, abs($limit));
        }

        return $this->slice(0, $limit);
    }

    /**
     * Take items until callback returns true.
     */
    public function takeUntil(callable $callback): static
    {
        return $this->takeWhile(fn($item, $key) => !$callback($item, $key));
    }

    /**
     * Take items while callback returns true.
     */
    public function takeWhile(callable $callback): static
    {
        $results = [];

        foreach ($this->items as $key => $item) {
            if (!$callback($item, $key)) {
                break;
            }
            $results[$key] = $item;
        }

        return new static($results);
    }

    /**
     * Skip {$count} items.
     */
    public function skip(int $count): static
    {
        return $this->slice($count);
    }

    public function skipWhile(callable $callback): static
    {
        $results = [];
        $skipping = true;

        foreach ($this->items as $key => $item) {
            if ($skipping) {
                if ($callback($item, $key)) {
                    continue;
                }
                $skipping = false;
            }
            $results[$key] = $item;
        }

        return new static($results);
    }

    public function skipUntil(callable $callback): static
    {
        return $this->skipWhile(fn($item, $key) => !$callback($item, $key));
    }

    /**
     * Slice for pagination (page starting at 1).
     */
    public function forPage(int $page, int $perPage): static
    {
        $offset = max(0, ($page - 1) * $perPage);
        return $this->slice($offset, $perPage);
    }

    /**
     * Transform collection items in place.
     */
    public function transform(callable $callback): static
    {
        $this->items = $this->map($callback)->all();
        return $this;
    }

    /**
     * Return only unique items.
     */
    public function unique(?string $key = null, bool $strict = false): static
    {
        if ($key === null) {
            return new static(array_unique($this->items, $strict ? SORT_REGULAR : SORT_STRING));
        }

        $exists = [];
        return $this->reject(function ($item) use ($key, $strict, &$exists) {
            $id = $this->dataGet($item, $key);
            if (in_array($id, $exists, $strict)) {
                return true;
            }
            $exists[] = $id;
            return false;
        });
    }

    /**
     * Reset array keys and return values.
     */
    public function values(): static
    {
        return new static(array_values($this->items));
    }

    /**
     * Filter items by a key-value condition (supports operators: '=', '!=', '>', '<', '>=', '<=', 'like', 'in', 'not in').
     */
    public function where(string $key, mixed $operator = null, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->filter(function ($item) use ($key, $operator, $value) {
            $retrieved = $this->dataGet($item, $key);

            return match (strtolower((string) $operator)) {
                '=' => $retrieved == $value,
                '==' => $retrieved == $value,
                '===' => $retrieved === $value,
                '!=' => $retrieved != $value,
                '<>' => $retrieved != $value,
                '!==' => $retrieved !== $value,
                '<' => $retrieved < $value,
                '>' => $retrieved > $value,
                '<=' => $retrieved <= $value,
                '>=' => $retrieved >= $value,
                'in' => in_array($retrieved, (array) $value),
                'not in' => !in_array($retrieved, (array) $value),
                'like' => is_string($retrieved) && fnmatch($value, $retrieved),
                default => $retrieved == $value,
            };
        });
    }

    public function whereIn(string $key, mixed $values, bool $strict = false): static
    {
        $values = $this->getArrayableItems($values);
        return $this->filter(fn($item) => in_array($this->dataGet($item, $key), $values, $strict));
    }

    public function whereNotIn(string $key, mixed $values, bool $strict = false): static
    {
        $values = $this->getArrayableItems($values);
        return $this->filter(fn($item) => !in_array($this->dataGet($item, $key), $values, $strict));
    }

    public function whereNull(string $key): static
    {
        return $this->filter(fn($item) => $this->dataGet($item, $key) === null);
    }

    public function whereNotNull(string $key): static
    {
        return $this->filter(fn($item) => $this->dataGet($item, $key) !== null);
    }

    public function whereBetween(string $key, array $values): static
    {
        return $this->filter(function ($item) use ($key, $values) {
            $val = $this->dataGet($item, $key);
            return $val >= $values[0] && $val <= $values[1];
        });
    }

    public function whereNotBetween(string $key, array $values): static
    {
        return $this->filter(function ($item) use ($key, $values) {
            $val = $this->dataGet($item, $key);
            return $val < $values[0] || $val > $values[1];
        });
    }

    public function whereLike(string $key, string $pattern): static
    {
        return $this->where($key, 'like', $pattern);
    }

    public function whereInstance(string $class): static
    {
        return $this->filter(fn($item) => $item instanceof $class);
    }

    /**
     * Tap into the collection and execute callback without modifying chain.
     */
    public function tap(callable $callback): static
    {
        $callback(new static($this->items));
        return $this;
    }

    /**
     * Apply callback conditionally if $value is truthy.
     */
    public function when(mixed $value = null, ?callable $callback = null, ?callable $default = null): static
    {
        if ($value) {
            return $callback ? $callback($this, $value) ?? $this : $this;
        }

        return $default ? $default($this, $value) ?? $this : $this;
    }

    /**
     * Apply callback conditionally if $value is falsy.
     */
    public function unless(mixed $value = null, ?callable $callback = null, ?callable $default = null): static
    {
        return $this->when(!$value, $callback, $default);
    }

    public function whenEmpty(callable $callback, ?callable $default = null): static
    {
        return $this->when($this->isEmpty(), $callback, $default);
    }

    public function whenNotEmpty(callable $callback, ?callable $default = null): static
    {
        return $this->when($this->isNotEmpty(), $callback, $default);
    }

    /**
     * Convert a flat list of parent-child records into a hierarchical tree structure in O(N) time.
     */
    public function toTree(string $parentKey = 'parent_id', string $idKey = 'id', string $childrenKey = 'children'): static
    {
        $items = $this->toArray();
        $indexed = [];
        $tree = [];

        foreach ($items as $item) {
            $id = $item[$idKey] ?? null;
            if ($id !== null) {
                $item[$childrenKey] = [];
                $indexed[$id] = $item;
            }
        }

        foreach ($indexed as $id => &$node) {
            $parentId = $node[$parentKey] ?? null;
            if ($parentId !== null && isset($indexed[$parentId])) {
                $indexed[$parentId][$childrenKey][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }

        return new static($tree);
    }

    /**
     * Flatten a hierarchical tree collection into a flat list.
     */
    public function flattenTree(string $childrenKey = 'children'): static
    {
        $flat = [];

        $walk = function ($items) use (&$walk, &$flat, $childrenKey) {
            foreach ($items as $item) {
                $children = $item[$childrenKey] ?? [];
                unset($item[$childrenKey]);
                $flat[] = $item;

                if (!empty($children)) {
                    $walk($children);
                }
            }
        };

        $walk($this->items);
        return new static($flat);
    }

    /**
     * Count items in collection.
     */
    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    public function toArray(): array
    {
        return array_map(function ($value) {
            return $value instanceof Enumerable ? $value->toArray() : (is_object($value) && method_exists($value, 'toArray') ? $value->toArray() : $value);
        }, $this->items);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options) ?: '[]';
    }

    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * Enable higher-order messaging proxy (e.g. $collection->map->name).
     */
    public function __get(string $key): mixed
    {
        if (in_array($key, ['map', 'filter', 'reject', 'each', 'sortBy', 'sortByDesc', 'unique'])) {
            return new HigherOrderCollectionProxy($this, $key);
        }

        return $this->get($key);
    }

    /**
     * Helper to retrieve dot notation value from array or object.
     */
    protected function dataGet(mixed $target, ?string $key = null, mixed $default = null): mixed
    {
        if ($key === null || $key === '') {
            return $target;
        }

        $segments = explode('.', $key);

        foreach ($segments as $segment) {
            if (is_array($target) && array_key_exists($segment, $target)) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } elseif (is_object($target) && method_exists($target, $segment)) {
                $target = $target->{$segment}();
            } else {
                return $default;
            }
        }

        return $target;
    }

    /**
     * Convert the given value to an array.
     */
    protected function getArrayableItems(mixed $items): array
    {
        if (is_array($items)) {
            return $items;
        }

        if ($items instanceof Enumerable) {
            return $items->all();
        }

        if ($items instanceof Traversable) {
            return iterator_to_array($items);
        }

        return (array) $items;
    }
}
