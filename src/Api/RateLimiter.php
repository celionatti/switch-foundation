<?php

declare(strict_types=1);

namespace Switch\Foundation\Api;

use Switch\Foundation\Cache\CacheManager;

class RateLimiter
{
    private static ?self $instance = null;
    private CacheManager $cache;

    public function __construct(?CacheManager $cache = null)
    {
        $this->cache = $cache ?? CacheManager::getInstance();
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->attempts($key) >= $maxAttempts;
    }

    public function hit(string $key, int $decaySeconds = 60): int
    {
        $hitsKey = $this->cleanKey($key);
        $timerKey = $hitsKey . ':timer';

        if (!$this->cache->has($timerKey)) {
            $this->cache->put($timerKey, time() + $decaySeconds, $decaySeconds);
        }

        $hits = (int) $this->cache->get($hitsKey, 0) + 1;
        $this->cache->put($hitsKey, $hits, $decaySeconds);

        return $hits;
    }

    public function attempts(string $key): int
    {
        return (int) $this->cache->get($this->cleanKey($key), 0);
    }

    public function resetAttempts(string $key): bool
    {
        $clean = $this->cleanKey($key);
        $this->cache->forget($clean);
        $this->cache->forget($clean . ':timer');
        return true;
    }

    public function remaining(string $key, int $maxAttempts): int
    {
        $attempts = $this->attempts($key);
        return max(0, $maxAttempts - $attempts);
    }

    public function availableIn(string $key): int
    {
        $timer = $this->cache->get($this->cleanKey($key) . ':timer');
        if ($timer === null) {
            return 0;
        }

        return max(0, (int) $timer - time());
    }

    public function cleanKey(string $key): string
    {
        return 'rate_limit:' . sha1($key);
    }
}
