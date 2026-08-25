<?php

declare(strict_types=1);

namespace Switch\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Switch\Foundation\Collection\Collection;
use Switch\Foundation\Collection\LazyCollection;

class CollectionTest extends TestCase
{
    public function testCollectionCreationAndBasics(): void
    {
        $c = collect([1, 2, 3]);
        $this->assertEquals(3, $c->count());
        $this->assertFalse($c->isEmpty());
        $this->assertTrue($c->isNotEmpty());
        $this->assertEquals([1, 2, 3], $c->all());

        // Times & Range
        $times = Collection::times(4, fn($i) => $i * 10);
        $this->assertEquals([10, 20, 30, 40], $times->all());

        $range = Collection::range(1, 5);
        $this->assertEquals([1, 2, 3, 4, 5], $range->all());

        // Wrap
        $this->assertEquals(['foo'], Collection::wrap('foo')->all());
        $this->assertEquals([], Collection::wrap(null)->all());
    }

    public function testFilteringAndWhere(): void
    {
        $users = collect([
            ['id' => 1, 'name' => 'Alice', 'role' => 'admin', 'age' => 30],
            ['id' => 2, 'name' => 'Bob', 'role' => 'editor', 'age' => 25],
            ['id' => 3, 'name' => 'Charlie', 'role' => 'member', 'age' => 35],
            ['id' => 4, 'name' => 'David', 'role' => 'member', 'age' => 20],
        ]);

        // Where equal
        $admins = $users->where('role', 'admin');
        $this->assertEquals(1, $admins->count());
        $this->assertEquals('Alice', $admins->first()['name']);

        // Where comparison
        $older = $users->where('age', '>=', 30)->pluck('name')->values()->all();
        $this->assertEquals(['Alice', 'Charlie'], $older);

        // WhereIn
        $staff = $users->whereIn('role', ['admin', 'editor'])->pluck('name')->values()->all();
        $this->assertEquals(['Alice', 'Bob'], $staff);

        // WhereBetween
        $midAge = $users->whereBetween('age', [24, 32])->pluck('name')->values()->all();
        $this->assertEquals(['Alice', 'Bob'], $midAge);

        // WhereLike
        $cNames = $users->whereLike('name', 'C*')->pluck('name')->values()->all();
        $this->assertEquals(['Charlie'], $cNames);

        // First & Last & Sole
        $this->assertEquals('David', $users->last()['name']);
        $this->assertEquals('Bob', $users->firstWhere('role', 'editor')['name']);
        $this->assertEquals('Alice', $users->where('id', 1)->sole()['name']);
    }

    public function testTransformationsAndPluck(): void
    {
        $items = collect([
            ['product' => ['title' => 'Laptop', 'price' => 1000]],
            ['product' => ['title' => 'Phone', 'price' => 500]],
            ['product' => ['title' => 'Tablet', 'price' => 300]],
        ]);

        // Deep dot-notation pluck
        $titles = $items->pluck('product.title')->all();
        $this->assertEquals(['Laptop', 'Phone', 'Tablet'], $titles);

        // MapWithKeys
        $priceMap = $items->mapWithKeys(fn($item) => [$item['product']['title'] => $item['product']['price']])->all();
        $this->assertEquals(['Laptop' => 1000, 'Phone' => 500, 'Tablet' => 300], $priceMap);

        // GroupBy
        $grouped = collect([
            ['cat' => 'A', 'val' => 1],
            ['cat' => 'B', 'val' => 2],
            ['cat' => 'A', 'val' => 3],
        ])->groupBy('cat');

        $this->assertEquals(2, $grouped->count());
        $this->assertEquals(2, $grouped['A']->count());

        // Partition
        [$adults, $minors] = collect([15, 22, 18, 12, 30])->partition(fn($age) => $age >= 18);
        $this->assertEquals([22, 18, 30], $adults->values()->all());
        $this->assertEquals([15, 12], $minors->values()->all());
    }

    public function testMathAndAggregations(): void
    {
        $nums = collect([10, 20, 30, 40, 50]);

        $this->assertEquals(150, $nums->sum());
        $this->assertEquals(30, $nums->avg());
        $this->assertEquals(30, $nums->median());
        $this->assertEquals(10, $nums->min());
        $this->assertEquals(50, $nums->max());

        $evenPercentage = $nums->percentage(fn($n) => $n >= 30);
        $this->assertEquals(60.0, $evenPercentage);
    }

    public function testSlicingAndChunkingAndSliding(): void
    {
        $letters = collect(['a', 'b', 'c', 'd', 'e', 'f']);

        // Chunk
        $chunks = $letters->chunk(2);
        $this->assertEquals(3, $chunks->count());
        $this->assertEquals(['a', 'b'], $chunks->first()->values()->all());

        // Sliding
        $windows = collect([1, 2, 3, 4])->sliding(2);
        $this->assertEquals(3, $windows->count());
        $this->assertEquals([1, 2], $windows[0]->values()->all());
        $this->assertEquals([2, 3], $windows[1]->values()->all());
        $this->assertEquals([3, 4], $windows[2]->values()->all());

        // Pagination
        $page2 = $letters->forPage(2, 2)->values()->all();
        $this->assertEquals(['c', 'd'], $page2);
    }

    public function testTreeStructureGeneration(): void
    {
        $categories = collect([
            ['id' => 1, 'name' => 'Electronics', 'parent_id' => null],
            ['id' => 2, 'name' => 'Laptops', 'parent_id' => 1],
            ['id' => 3, 'name' => 'Gaming Laptops', 'parent_id' => 2],
            ['id' => 4, 'name' => 'Books', 'parent_id' => null],
        ]);

        $tree = $categories->toTree();

        $this->assertEquals(2, $tree->count()); // Electronics & Books
        $this->assertEquals('Electronics', $tree[0]['name']);
        $this->assertEquals('Laptops', $tree[0]['children'][0]['name']);
        $this->assertEquals('Gaming Laptops', $tree[0]['children'][0]['children'][0]['name']);

        // Flatten Tree back
        $flat = $tree->flattenTree();
        $this->assertEquals(4, $flat->count());
    }

    public function testHigherOrderProxy(): void
    {
        $users = collect([
            (object) ['id' => 1, 'name' => 'Alice'],
            (object) ['id' => 2, 'name' => 'Bob'],
        ]);

        $names = $users->map->name->all();
        $this->assertEquals(['Alice', 'Bob'], $names);
    }

    public function testLazyCollection(): void
    {
        $lazy = LazyCollection::times(100)
            ->filter(fn($i) => $i % 2 === 0)
            ->map(fn($i) => $i * 10)
            ->take(3);

        $this->assertInstanceOf(LazyCollection::class, $lazy);
        $eager = $lazy->eager();
        $this->assertEquals([20, 40, 60], $eager->values()->all());
    }
}
