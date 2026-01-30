<?php

declare(strict_types=1);

namespace Cpts\Api\Packagist;

use Cpts\Api\Packagist\Dto\Package;
use Cpts\Api\Packagist\Dto\Stats;

interface PackagistClientInterface
{
    /**
     * Get package metadata.
     */
    public function getPackage(string $vendor, string $package): Package;

    /**
     * Get package statistics (downloads, dependents).
     */
    public function getStats(string $vendor, string $package): Stats;

    /**
     * Get number of packages that depend on this package.
     */
    public function getDependentsCount(string $vendor, string $package): int;

    /**
     * Check if a package exists on Packagist.
     */
    public function packageExists(string $vendor, string $package): bool;
}
