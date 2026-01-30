<?php

declare(strict_types=1);

namespace Cpts\Config;

use Composer\Composer;

class ComposerConfig implements ConfigInterface
{
    private const DEFAULT_MIN_CPTS = 20;
    private const DEFAULT_CACHE_TTL = 3600;
    private const CONFIG_KEY = 'cpts';

    /** @var array<string, mixed> */
    private array $config;
    private string $vendorDir;

    public function __construct(Composer $composer)
    {
        $extra = $composer->getPackage()->getExtra();
        $this->config = $extra[self::CONFIG_KEY] ?? [];
        $this->vendorDir = $composer->getConfig()->get('vendor-dir');
    }

    public function getMinCpts(): int
    {
        return (int) ($this->config['min_cpts'] ?? self::DEFAULT_MIN_CPTS);
    }

    public function getTrustedPackages(): array
    {
        $trusted = $this->config['trusted_packages'] ?? [];

        return is_array($trusted) ? $trusted : [];
    }

    public function getGitHubToken(): ?string
    {
        // 1. Environment variable (highest priority)
        $envToken = getenv('GITHUB_TOKEN');
        if ($envToken !== false && $envToken !== '') {
            return $envToken;
        }

        // 2. .env file in project root
        $envFile = dirname($this->vendorDir) . '/.env';
        if (file_exists($envFile)) {
            $token = $this->parseEnvFile($envFile, 'GITHUB_TOKEN');
            if ($token !== null) {
                return $token;
            }
        }

        return null;
    }

    private function parseEnvFile(string $path, string $key): ?string
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return null;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, $key . '=')) {
                $value = substr($line, strlen($key) + 1);
                // Remove quotes if present
                return trim($value, '"\'');
            }
        }

        return null;
    }

    public function getCacheTtl(): int
    {
        return (int) ($this->config['cache_ttl'] ?? self::DEFAULT_CACHE_TTL);
    }

    public function isStrictMode(): bool
    {
        return false; // Always warn-only, never block
    }

    public function getCacheDir(): string
    {
        if (isset($this->config['cache_dir'])) {
            return (string) $this->config['cache_dir'];
        }

        return dirname($this->vendorDir) . '/.cpts-cache';
    }

    public function isDisabled(): bool
    {
        $envDisable = getenv('CPTS_DISABLE');

        return $envDisable !== false && $envDisable !== '' && $envDisable !== '0';
    }

    public function getMetricWeights(): array
    {
        $weights = $this->config['weights'] ?? [];

        return is_array($weights) ? $weights : [];
    }
}
