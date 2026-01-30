<?php

declare(strict_types=1);

namespace Cpts\Cache;

/**
 * No-op cache implementation for testing.
 */
class NullCache implements CacheInterface
{
    public function get(string $key): mixed
    {
        return null;
    }

    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        // No-op
    }

    public function has(string $key): bool
    {
        return false;
    }

    public function delete(string $key): void
    {
        // No-op
    }

    public function clear(): void
    {
        // No-op
    }

    public function getStale(string $key): mixed
    {
        return null;
    }
}
