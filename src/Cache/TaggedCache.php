<?php

declare(strict_types=1);

namespace Switch\Foundation\Cache;

use Switch\Foundation\Cache\Store\CacheStoreInterface;

class TaggedCache
{
    private CacheStoreInterface $store;
    private array $tags;

    public function __construct(CacheStoreInterface $store, array $tags)
    {
        $this->store = $store;
        $this->tags = $tags;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store->get($this->taggedKey($key), $default);
    }

    public function put(string $key, mixed $value, int $seconds = 0): bool
    {
        return $this->store->put($this->taggedKey($key), $value, $seconds);
    }

    public function has(string $key): bool
    {
        return $this->store->has($this->taggedKey($key));
    }

    public function forget(string $key): bool
    {
        return $this->store->forget($this->taggedKey($key));
    }

    public function remember(string $key, int $seconds, callable $callback): mixed
    {
        return $this->store->remember($this->taggedKey($key), $seconds, $callback);
    }

    public function flush(): bool
    {
        foreach ($this->tags as $tag) {
            $this->store->increment($this->tagVersionKey($tag));
        }
        return true;
    }

    protected function taggedKey(string $key): string
    {
        $versions = [];
        foreach ($this->tags as $tag) {
            $versions[] = $tag . ':' . $this->getTagVersion($tag);
        }
        return 'tag:' . implode('|', $versions) . ':' . $key;
    }

    protected function getTagVersion(string $tag): string
    {
        $key = $this->tagVersionKey($tag);
        $version = $this->store->get($key);

        if ($version === null) {
            $version = (string) microtime(true);
            $this->store->put($key, $version, 86400 * 365);
        }

        return (string) $version;
    }

    protected function tagVersionKey(string $tag): string
    {
        return '_tag_ver:' . $tag;
    }
}
