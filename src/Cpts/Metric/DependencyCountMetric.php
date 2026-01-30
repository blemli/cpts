<?php

declare(strict_types=1);

namespace Cpts\Metric;

use Cpts\Package\PackageInfo;
use Cpts\Score\MetricResult;

/**
 * Dependency Count Metric
 *
 * Fewer dependencies = better (reduced attack surface, simpler maintenance)
 * deps_norm = 1 - min(direct_deps / 20, 1)
 *
 * 0 deps = 1.0 score, 20+ deps = 0.0 score
 */
class DependencyCountMetric extends AbstractMetric
{
    private const MAX_ACCEPTABLE_DEPS = 20;

    public function getName(): string
    {
        return 'dependency_count';
    }

    public function getDescription(): string
    {
        return 'Direct dependency count (fewer is better)';
    }

    public function getDefaultWeight(): float
    {
        return 3.0;
    }

    public function isHigherBetter(): bool
    {
        return false; // More dependencies = worse
    }

    public function isApplicable(PackageInfo $package): bool
    {
        return $package->hasPackagistData();
    }

    public function calculate(PackageInfo $package): MetricResult
    {
        $directDeps = $package->getDirectDependencyCount();

        // Inverse normalization: 1 - min(deps/max, 1)
        $normalized = 1.0 - min($directDeps / self::MAX_ACCEPTABLE_DEPS, 1.0);

        return $this->result($normalized, [
            'direct_dependencies' => $directDeps,
            'max_acceptable' => self::MAX_ACCEPTABLE_DEPS,
        ]);
    }
}
