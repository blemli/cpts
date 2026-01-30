<?php

declare(strict_types=1);

namespace Cpts\Config;

interface ConfigInterface
{
    /**
     * Minimum CPTS score required for packages.
     */
    public function getMinCpts(): int;

    /**
     * List of trusted package patterns (supports wildcards).
     *
     * @return string[]
     */
    public function getTrustedPackages(): array;

    /**
     * GitHub API token for higher rate limits.
     */
    public function getGitHubToken(): ?string;

    /**
     * Cache TTL in seconds.
     */
    public function getCacheTtl(): int;

    /**
     * Whether to fail on packages below min_cpts.
     */
    public function isStrictMode(): bool;

    /**
     * Path to cache directory.
     */
    public function getCacheDir(): string;

    /**
     * Whether CPTS checks are disabled (via CPTS_DISABLE env var).
     */
    public function isDisabled(): bool;

    /**
     * Custom metric weights (overrides defaults).
     *
     * @return array<string, float>
     */
    public function getMetricWeights(): array;
}
