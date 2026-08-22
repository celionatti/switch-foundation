<?php

declare(strict_types=1);

namespace Switch\Foundation\Storage;

class LocalFilesystem implements FilesystemInterface
{
    private string $root;
    private string $baseUrl;

    public function __construct(string $root, string $baseUrl = '')
    {
        $this->root = rtrim(str_replace('\\', '/', $root), '/');
        $this->baseUrl = rtrim($baseUrl, '/');

        if (!is_dir($this->root)) {
            @mkdir($this->root, 0777, true);
        }
    }

    public function exists(string $path): bool
    {
        return file_exists($this->path($path));
    }

    public function missing(string $path): bool
    {
        return !$this->exists($path);
    }

    public function get(string $path): ?string
    {
        $fullPath = $this->path($path);
        if (!file_exists($fullPath)) {
            return null;
        }

        $contents = @file_get_contents($fullPath);
        return $contents !== false ? $contents : null;
    }

    public function put(string $path, string $contents): bool
    {
        $fullPath = $this->path($path);
        $this->ensureDirectory(dirname($fullPath));

        return @file_put_contents($fullPath, $contents, LOCK_EX) !== false;
    }

    public function prepend(string $path, string $data): bool
    {
        if ($this->exists($path)) {
            return $this->put($path, $data . $this->get($path));
        }

        return $this->put($path, $data);
    }

    public function append(string $path, string $data): bool
    {
        $fullPath = $this->path($path);
        $this->ensureDirectory(dirname($fullPath));

        return @file_put_contents($fullPath, $data, FILE_APPEND | LOCK_EX) !== false;
    }

    public function delete(array|string $paths): bool
    {
        $paths = is_array($paths) ? $paths : [$paths];
        $success = true;

        foreach ($paths as $path) {
            $fullPath = $this->path($path);
            if (file_exists($fullPath)) {
                if (!@unlink($fullPath)) {
                    $success = false;
                }
            }
        }

        return $success;
    }

    public function copy(string $from, string $to): bool
    {
        $fromPath = $this->path($from);
        $toPath = $this->path($to);

        if (!file_exists($fromPath)) {
            return false;
        }

        $this->ensureDirectory(dirname($toPath));
        return @copy($fromPath, $toPath);
    }

    public function move(string $from, string $to): bool
    {
        $fromPath = $this->path($from);
        $toPath = $this->path($to);

        if (!file_exists($fromPath)) {
            return false;
        }

        $this->ensureDirectory(dirname($toPath));
        return @rename($fromPath, $toPath);
    }

    public function size(string $path): int
    {
        $fullPath = $this->path($path);
        return file_exists($fullPath) ? (int) @filesize($fullPath) : 0;
    }

    public function lastModified(string $path): int
    {
        $fullPath = $this->path($path);
        return file_exists($fullPath) ? (int) @filemtime($fullPath) : 0;
    }

    public function files(string $directory = '', bool $recursive = false): array
    {
        $dir = $this->path($directory);
        if (!is_dir($dir)) {
            return [];
        }

        $results = [];
        $iterator = $recursive
            ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS))
            : new \DirectoryIterator($dir);

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $results[] = $this->relativePath($item->getPathname());
            }
        }

        return $results;
    }

    public function directories(string $directory = '', bool $recursive = false): array
    {
        $dir = $this->path($directory);
        if (!is_dir($dir)) {
            return [];
        }

        $results = [];
        $iterator = $recursive
            ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST)
            : new \DirectoryIterator($dir);

        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isDot()) {
                $results[] = $this->relativePath($item->getPathname());
            }
        }

        return $results;
    }

    public function makeDirectory(string $path): bool
    {
        $fullPath = $this->path($path);
        return $this->ensureDirectory($fullPath);
    }

    public function deleteDirectory(string $directory): bool
    {
        $dir = $this->path($directory);
        if (!is_dir($dir)) {
            return true;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $p = $dir . '/' . $file;
            is_dir($p) ? $this->deleteDirectory($this->relativePath($p)) : @unlink($p);
        }

        return @rmdir($dir);
    }

    public function url(string $path): string
    {
        $normalized = ltrim($path, '/\\');
        return $this->baseUrl . '/' . $normalized;
    }

    public function path(string $path): string
    {
        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        return $normalized === '' ? $this->root : $this->root . '/' . $normalized;
    }

    public function mimeType(string $path): ?string
    {
        $fullPath = $this->path($path);
        if (!file_exists($fullPath)) {
            return null;
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $fullPath);
            finfo_close($finfo);
            return $mime !== false ? $mime : null;
        }

        return match (pathinfo($fullPath, PATHINFO_EXTENSION)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'json' => 'application/json',
            'txt' => 'text/plain',
            default => 'application/octet-stream',
        };
    }

    public function putFile(string $path, string $file): string|false
    {
        if (!file_exists($file)) {
            return false;
        }

        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $filename = uniqid('file_', true) . ($ext ? '.' . $ext : '');
        $target = rtrim($path, '/\\') . '/' . $filename;

        if ($this->put($target, file_get_contents($file))) {
            return $target;
        }

        return false;
    }

    private function ensureDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return @mkdir($dir, 0777, true);
        }
        return true;
    }

    private function relativePath(string $pathname): string
    {
        $normalized = str_replace('\\', '/', $pathname);
        $prefix = $this->root . '/';
        if (str_starts_with($normalized, $prefix)) {
            return substr($normalized, strlen($prefix));
        }
        return $normalized;
    }
}
