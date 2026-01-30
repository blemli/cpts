<?php

declare(strict_types=1);

namespace Cpts\Metric;

use Cpts\Package\PackageInfo;
use Cpts\Score\MetricResult;

/**
 * Dependents Metric
 *
 * Production adoption signal from Packagist dependents count.
 * deps_norm = min(log10(dependents + 1) / 4, 1)
 *
 * ~10,000 dependents = full score
 */
class DependentsMetric extends AbstractMetric
{
    private const LOG_DIVISOR = 4.0;

    public function getName(): string
    {
        return 'dependents';
    }

    public function getDescription(): string
    {
        return 'Packages that depend on this (production adoption)';
    }

    public function getDefaultWeight(): float
    {
        return 2.0;
    }

    public function isApplicable(PackageInfo $package): bool
    {
        return $package->hasPackagistData();
    }

    public function calculate(PackageInfo $package): MetricResult
    {
        $dependents = $package->getDependentsCount();

        // deps_norm = min(log10(p+1)/4, 1)
        $normalized = min(log10($dependents + 1) / self::LOG_DIVISOR, 1.0);

        return $this->result($normalized, [
            'dependents' => $dependents,
            'log_value' => round(log10($dependents + 1), 3),
        ]);
    }
}
