<?php

declare(strict_types=1);

namespace Cpts\Metric;

use Cpts\Package\PackageInfo;
use Cpts\Score\MetricResult;

/**
 * Repository Age Metric
 *
 * Maturity and survival signal.
 * age_norm = min(years_since_first_commit / 5, 1)
 *
 * 5+ years old = full score
 */
class RepoAgeMetric extends AbstractMetric
{
    private const MAX_YEARS = 5.0;

    public function getName(): string
    {
        return 'repo_age';
    }

    public function getDescription(): string
    {
        return 'Repository age (maturity signal)';
    }

    public function getDefaultWeight(): float
    {
        return 2.0;
    }

    public function getEmoji(): string
    {
        return '🕰️';
    }

    public function isApplicable(PackageInfo $package): bool
    {
        return $package->hasGitHubData();
    }

    public function calculate(PackageInfo $package): MetricResult
    {
        $ageInYears = $package->getAgeInYears();

        // age_norm = min(years/5, 1)
        $normalized = min($ageInYears / self::MAX_YEARS, 1.0);

        return $this->result($normalized, [
            'age_years' => round($ageInYears, 2),
            'first_commit_date' => $package->getFirstCommitDate()?->format('Y-m-d'),
        ]);
    }
}
