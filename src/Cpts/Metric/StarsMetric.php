<?php

declare(strict_types=1);

namespace Cpts\Metric;

use Cpts\Package\PackageInfo;
use Cpts\Score\MetricResult;

/**
 * Stars Metric
 *
 * Lightweight attention signal from GitHub stars.
 * stars_norm = min(log10(stars + 1) / 4, 1)
 *
 * ~10,000 stars = full score
 */
class StarsMetric extends AbstractMetric
{
    private const LOG_DIVISOR = 4.0;

    public function getName(): string
    {
        return 'stars';
    }

    public function getDescription(): string
    {
        return 'GitHub stars (attention signal)';
    }

    public function getDefaultWeight(): float
    {
        return 1.0;
    }

    public function getEmoji(): string
    {
        return '⭐';
    }

    public function isApplicable(PackageInfo $package): bool
    {
        return $package->hasGitHubData();
    }

    public function calculate(PackageInfo $package): MetricResult
    {
        $stars = $package->getStarsCount();

        // stars_norm = min(log10(s+1)/4, 1)
        $normalized = min(log10($stars + 1) / self::LOG_DIVISOR, 1.0);

        return $this->result($normalized, [
            'stars' => $stars,
            'log_value' => round(log10($stars + 1), 3),
        ]);
    }
}
