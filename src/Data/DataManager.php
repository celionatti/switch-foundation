<?php

declare(strict_types=1);

namespace Switch\Foundation\Data;

class DataManager
{
    /**
     * @var array<string, mixed> Loaded in-memory cache of datasets
     */
    private array $datasets = [];

    /**
     * @var array<int, string> Directories searched for static data files
     */
    private array $searchPaths = [];

    /**
     * @var array<string, callable> Registered mock blueprints
     */
    private array $blueprints = [];

    private MockGenerator $faker;

    public function __construct(array $paths = [])
    {
        $this->faker = new MockGenerator();

        // Default standard data search paths
        $base = defined('SWITCH_BASE_PATH') ? SWITCH_BASE_PATH : (getcwd() ?: '.');
        $this->searchPaths = array_unique(array_filter([
            $base . '/data',
            $base . '/resources/data',
            $base . '/app/Data',
            ...$paths
        ]));

        $this->registerDefaultBlueprints();
    }

    /**
     * Add a directory to search for static data files.
     */
    public function addPath(string $path): self
    {
        $real = rtrim($path, '/\\');
        if (!in_array($real, $this->searchPaths, true)) {
            $this->searchPaths[] = $real;
        }
        return $this;
    }

    /**
     * Get the Mock generator instance.
     */
    public function faker(): MockGenerator
    {
        return $this->faker;
    }

    /**
     * Generate fake data value by type name (e.g. 'email', 'name', 'avatar').
     */
    public function fake(?string $type = null, ...$args): mixed
    {
        if ($type === null) {
            return $this->faker;
        }

        if (method_exists($this->faker, $type)) {
            return $this->faker->$type(...$args);
        }

        return $this->mock($type, 1, $args[0] ?? [])[0] ?? null;
    }

    /**
     * Define a named blueprint factory for mock records.
     */
    public function define(string $blueprint, callable $factory): self
    {
        $this->blueprints[$blueprint] = $factory;
        return $this;
    }

    /**
     * Generate mock data records using a registered blueprint or default schema.
     *
     * @return array<int, array<string, mixed>>
     */
    public function mock(string $blueprint, int $count = 1, array $overrides = []): array
    {
        $records = [];
        $factory = $this->blueprints[$blueprint] ?? null;

        for ($i = 1; $i <= $count; $i++) {
            if ($factory !== null) {
                $record = $factory($i, $this->faker);
            } else {
                $record = $this->createGenericMock($blueprint, $i);
            }

            if (!empty($overrides)) {
                $record = array_merge($record, $overrides);
            }

            $records[] = $record;
        }

        return $records;
    }

    /**
     * Load a dataset from file (JSON, PHP, or CSV) with dot-notation caching.
     */
    public function load(string $source): mixed
    {
        if (isset($this->datasets[$source])) {
            return $this->datasets[$source];
        }

        foreach ($this->searchPaths as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            // 1. PHP file: e.g. countries.php
            $phpFile = $dir . '/' . $source . '.php';
            if (file_exists($phpFile)) {
                $data = require $phpFile;
                $this->datasets[$source] = $data;
                return $data;
            }

            // 2. JSON file: e.g. countries.json
            $jsonFile = $dir . '/' . $source . '.json';
            if (file_exists($jsonFile)) {
                $raw = file_get_contents($jsonFile);
                $data = $raw !== false ? json_decode($raw, true) : [];
                $this->datasets[$source] = $data;
                return $data;
            }

            // 3. CSV file: e.g. countries.csv
            $csvFile = $dir . '/' . $source . '.csv';
            if (file_exists($csvFile)) {
                $data = $this->parseCsv($csvFile);
                $this->datasets[$source] = $data;
                return $data;
            }
        }

        return null;
    }

    /**
     * Retrieve static data by source and key using dot-notation.
     * e.g. `get('countries.US.name')`
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (str_contains($key, '.')) {
            [$source, $subKey] = explode('.', $key, 2);
        } else {
            $source = $key;
            $subKey = null;
        }

        $data = $this->datasets[$source] ?? $this->load($source);

        if ($data === null) {
            return $default;
        }

        if ($subKey === null || $subKey === '') {
            return $data;
        }

        $segments = explode('.', $subKey);
        $target = $data;

        foreach ($segments as $segment) {
            if (is_array($target) && array_key_exists($segment, $target)) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return $default;
            }
        }

        return $target;
    }

    /**
     * Store/inject an in-memory dataset.
     */
    public function set(string $key, mixed $value): self
    {
        $this->datasets[$key] = $value;
        return $this;
    }

    /**
     * Check if a dataset or key exists.
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Query dataset rows where a field equals a value.
     */
    public function where(string $source, string $field, mixed $value): array
    {
        $records = (array) $this->get($source, []);
        $results = [];

        foreach ($records as $row) {
            if (is_array($row) && isset($row[$field]) && $row[$field] == $value) {
                $results[] = $row;
            } elseif (is_object($row) && isset($row->{$field}) && $row->{$field} == $value) {
                $results[] = $row;
            }
        }

        return $results;
    }

    /**
     * Find a single record in a dataset by primary identifier.
     */
    public function find(string $source, mixed $id, string $idField = 'id'): ?array
    {
        $rows = $this->where($source, $idField, $id);
        return !empty($rows) ? (array) $rows[0] : null;
    }

    /**
     * Pluck a single column from a dataset.
     */
    public function pluck(string $source, string $column, ?string $indexKey = null): array
    {
        $records = (array) $this->get($source, []);
        $out = [];

        foreach ($records as $row) {
            $val = is_array($row) ? ($row[$column] ?? null) : ($row->{$column} ?? null);
            if ($indexKey !== null) {
                $k = is_array($row) ? ($row[$indexKey] ?? null) : ($row->{$indexKey} ?? null);
                if ($k !== null) {
                    $out[(string) $k] = $val;
                    continue;
                }
            }
            $out[] = $val;
        }

        return $out;
    }

    /**
     * Get all cached in-memory datasets.
     */
    public function all(): array
    {
        return $this->datasets;
    }

    /**
     * Clear all cached datasets (for testing).
     */
    public function clear(): void
    {
        $this->datasets = [];
    }

    /**
     * Parse a CSV file into array of associative records using header row.
     */
    private function parseCsv(string $filePath): array
    {
        $rows = [];
        if (($handle = fopen($filePath, 'r')) !== false) {
            $headers = fgetcsv($handle);
            if ($headers !== false) {
                while (($data = fgetcsv($handle)) !== false) {
                    if (count($headers) === count($data)) {
                        $rows[] = array_combine($headers, $data);
                    }
                }
            }
            fclose($handle);
        }
        return $rows;
    }

    /**
     * Fallback generic mock record generator for common entity types.
     */
    private function createGenericMock(string $entity, int $id): array
    {
        return match (strtolower($entity)) {
            'user', 'users' => [
                'id' => $id,
                'name' => $this->faker->name(),
                'email' => $this->faker->email(),
                'avatar' => $this->faker->avatar((string) $id),
                'role' => $id === 1 ? 'admin' : 'member',
                'created_at' => $this->faker->dateTime(),
            ],
            'product', 'products' => [
                'id' => $id,
                'title' => $this->faker->title(3),
                'description' => $this->faker->paragraph(2),
                'price' => $this->faker->price(10, 300),
                'image' => $this->faker->image(400, 300, 'tech'),
                'stock' => $this->faker->integer(0, 100),
                'rating' => $this->faker->float(3.5, 5.0, 1),
            ],
            'post', 'posts' => [
                'id' => $id,
                'title' => $this->faker->title(5),
                'content' => $this->faker->paragraph(4),
                'author' => $this->faker->name(),
                'cover_image' => $this->faker->image(800, 450, 'nature'),
                'published_at' => $this->faker->dateTime(),
            ],
            'comment', 'comments' => [
                'id' => $id,
                'author' => $this->faker->name(),
                'comment' => $this->faker->sentence(12),
                'created_at' => $this->faker->dateTime(),
            ],
            default => [
                'id' => $id,
                'title' => $this->faker->title(3),
                'description' => $this->faker->sentence(8),
                'created_at' => $this->faker->dateTime(),
            ],
        };
    }

    /**
     * Register default high-velocity blueprints for immediate prototyping.
     */
    private function registerDefaultBlueprints(): void
    {
        $this->define('user', fn($i, $f) => [
            'id' => $i,
            'name' => $f->name(),
            'email' => $f->email(),
            'avatar' => $f->avatar((string) $i),
            'role' => $i === 1 ? 'admin' : 'member',
            'created_at' => $f->dateTime(),
        ]);

        $this->define('product', fn($i, $f) => [
            'id' => $i,
            'title' => $f->title(3),
            'description' => $f->paragraph(2),
            'price' => $f->price(15, 450),
            'image' => $f->image(400, 300, 'tech'),
            'stock' => $f->integer(5, 75),
            'rating' => $f->float(4.0, 5.0, 1),
        ]);

        $this->define('post', fn($i, $f) => [
            'id' => $i,
            'title' => $f->title(5),
            'slug' => strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $f->title(4))),
            'content' => $f->paragraph(4),
            'author' => $f->name(),
            'published_at' => $f->dateTime(),
        ]);
    }
}
