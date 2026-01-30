<?php

declare(strict_types=1);

namespace Cpts\Metric;

use Cpts\Package\PackageInfo;
use Cpts\Score\MetricResult;

/**
 * Active Committers Metric (Bus Factor)
 *
 * Measures contributor diversity:
 * committers_norm = min(unique_committers_last_180d / 5, 1)
 *
 * More unique active contributors = lower bus factor risk.
 */
class CommittersMetric extends AbstractMetric
{
    private const MAX_COMMITTERS = 5;

    public function getName(): string
    {
        return 'committers';
    }

    public function getDescription(): string
    {
        return 'Active committer count (bus factor)';
    }

    public function getDefaultWeight(): float
    {
        return 5.0;
    }

    public function isApplicable(PackageInfo $package): bool
    {
        return $package->hasGitHubData();
    }

    public function calculate(PackageInfo $package): MetricResult
    {
        $uniqueCommitters = $package->getUniqueCommittersLast180Days();

        // committers_norm = min(u/5, 1)
        $normalized = min($uniqueCommitters / self::MAX_COMMITTERS, 1.0);

        return $this->result($normalized, [
            'unique_committers_180d' => $uniqueCommitters,
            'max_for_full_score' => self::MAX_COMMITTERS,
        ]);
    }
}
