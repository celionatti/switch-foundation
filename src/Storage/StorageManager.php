<?php

declare(strict_types=1);

namespace Switch\Foundation\Storage;

use InvalidArgumentException;

class StorageManager
{
    private static ?self $instance = null;
    private array $config;
    private array $disks = [];
    private array $customDrivers = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'default' => 'local',
            'disks' => [
                'local' => [
                    'driver' => 'local',
                    'root' => 'storage/app',
                ],
                'public' => [
                    'driver' => 'local',
                    'root' => 'storage/app/public',
                    'url' => '/storage',
                ],
            ],
        ], $config);
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }

    public function disk(?string $name = null): FilesystemInterface
    {
        $name ??= $this->getDefaultDisk();

        if (isset($this->disks[$name])) {
            return $this->disks[$name];
        }

        return $this->disks[$name] = $this->resolve($name);
    }

    public function extend(string $driver, callable $callback): static
    {
        $this->customDrivers[$driver] = $callback;
        return $this;
    }

    public function getDefaultDisk(): string
    {
        return $this->config['default'] ?? 'local';
    }

    public function setDefaultDisk(string $name): void
    {
        $this->config['default'] = $name;
    }

    public function get(string $path): ?string
    {
        return $this->disk()->get($path);
    }

    public function put(string $path, string $contents): bool
    {
        return $this->disk()->put($path, $contents);
    }

    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
    }

    public function delete(array|string $paths): bool
    {
        return $this->disk()->delete($paths);
    }

    public function url(string $path): string
    {
        return $this->disk()->url($path);
    }

    public function path(string $path): string
    {
        return $this->disk()->path($path);
    }

    private function resolve(string $name): FilesystemInterface
    {
        $config = $this->config['disks'][$name] ?? null;

        if ($config === null) {
            throw new InvalidArgumentException("Disk [{$name}] is not configured.");
        }

        $driver = $config['driver'] ?? 'local';

        if (isset($this->customDrivers[$driver])) {
            return ($this->customDrivers[$driver])($this, $config);
        }

        return match ($driver) {
            'local' => new LocalFilesystem(
                $config['root'] ?? 'storage/app',
                $config['url'] ?? ''
            ),
            default => throw new InvalidArgumentException("Filesystem driver [{$driver}] is not supported."),
        };
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this->disk()->$method(...$parameters);
    }
}
