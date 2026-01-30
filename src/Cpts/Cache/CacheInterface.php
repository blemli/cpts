<?php

declare(strict_types=1);

namespace Cpts\Cache;

interface CacheInterface
{
    /**
     * Retrieve a cached value.
     *
     * @return mixed Returns null if not found or expired
     */
    public function get(string $key): mixed;

    /**
     * Store a value in cache.
     *
     * @param int $ttl Time-to-live in seconds
     */
    public function set(string $key, mixed $value, int $ttl = 3600): void;

    /**
     * Check if a key exists and is not expired.
     */
    public function has(string $key): bool;

    /**
     * Remove a cached value.
     */
    public function delete(string $key): void;

    /**
     * Clear all cached values.
     */
    public function clear(): void;

    /**
     * Get a value, falling back to stale cache if available.
     * Useful for graceful degradation when APIs are unavailable.
     */
    public function getStale(string $key): mixed;
}
