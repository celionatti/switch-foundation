<?php

declare(strict_types=1);

namespace Switch\Foundation\Storage;

interface FilesystemInterface
{
    public function exists(string $path): bool;

    public function missing(string $path): bool;

    public function get(string $path): ?string;

    public function put(string $path, string $contents): bool;

    public function prepend(string $path, string $data): bool;

    public function append(string $path, string $data): bool;

    public function delete(array|string $paths): bool;

    public function copy(string $from, string $to): bool;

    public function move(string $from, string $to): bool;

    public function size(string $path): int;

    public function lastModified(string $path): int;

    public function files(string $directory = '', bool $recursive = false): array;

    public function directories(string $directory = '', bool $recursive = false): array;

    public function makeDirectory(string $path): bool;

    public function deleteDirectory(string $directory): bool;

    public function url(string $path): string;

    public function path(string $path): string;

    public function mimeType(string $path): ?string;

    public function putFile(string $path, string $file): string|false;
}
