<?php

declare(strict_types=1);

namespace Cpts\Cache;

class FilesystemCache implements CacheInterface
{
    private const CACHE_VERSION = 1;

    public function __construct(
        private readonly string $cacheDir,
    ) {
    }

    public function get(string $key): mixed
    {
        $data = $this->readCacheFile($key);
        if ($data === null) {
            return null;
        }

        if ($data['expires_at'] < time()) {
            return null;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        $this->ensureCacheDir();

        $data = [
            'version' => self::CACHE_VERSION,
            'created_at' => time(),
            'expires_at' => time() + $ttl,
            'value' => $value,
        ];

        $path = $this->getPath($key);
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, serialize($data), LOCK_EX);
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function delete(string $key): void
    {
        $path = $this->getPath($key);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function clear(): void
    {
        if (!is_dir($this->cacheDir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->cacheDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
    }

    public function getStale(string $key): mixed
    {
        $data = $this->readCacheFile($key);
        if ($data === null) {
            return null;
        }

        return $data['value'];
    }

    private function getPath(string $key): string
    {
        $hash = hash('sha256', $key);
        $subdir = substr($hash, 0, 2);

        return $this->cacheDir . '/' . $subdir . '/' . $hash . '.cache';
    }

    private function ensureCacheDir(): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * @return array{version: int, created_at: int, expires_at: int, value: mixed}|null
     */
    private function readCacheFile(string $key): ?array
    {
        $path = $this->getPath($key);
        if (!file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        try {
            $data = unserialize($content);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($data) || !isset($data['version'], $data['value'])) {
            return null;
        }

        if ($data['version'] !== self::CACHE_VERSION) {
            return null;
        }

        return $data;
    }
}
