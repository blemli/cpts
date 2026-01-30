<?php

declare(strict_types=1);

namespace Cpts\Metric;

use Cpts\Package\PackageInfo;
use Cpts\Score\MetricResult;

/**
 * Activity Metric
 *
 * Measures project liveliness based on:
 * - Recency: How recent was the last commit?
 * - Cadence: How many commits in the last 90 days?
 *
 * Formula:
 * recency = exp(-days_since_last_commit / 180)
 * cadence = min(commits_last_90d / 20, 1)
 * activity_norm = 0.6 * recency + 0.4 * cadence
 */
class ActivityMetric extends AbstractMetric
{
    private const RECENCY_HALF_LIFE_DAYS = 180;
    private const CADENCE_MAX_COMMITS = 20;
    private const RECENCY_WEIGHT = 0.6;
    private const CADENCE_WEIGHT = 0.4;

    public function getName(): string
    {
        return 'activity';
    }

    public function getDescription(): string
    {
        return 'Project liveliness based on commit recency and frequency';
    }

    public function getDefaultWeight(): float
    {
        return 4.0;
    }

    public function getEmoji(): string
    {
        return '🗓️';
    }

    public function isApplicable(PackageInfo $package): bool
    {
        return $package->hasGitHubData();
    }

    public function calculate(PackageInfo $package): MetricResult
    {
        $daysSinceLastCommit = $package->getDaysSinceLastCommit();
        $commitsLast90Days = $package->getCommitsLast90Days();

        // recency = exp(-d/180)
        $recency = exp(-$daysSinceLastCommit / self::RECENCY_HALF_LIFE_DAYS);

        // cadence = min(c/20, 1)
        $cadence = min($commitsLast90Days / self::CADENCE_MAX_COMMITS, 1.0);

        // activity_norm = 0.6*recency + 0.4*cadence
        $normalized = (self::RECENCY_WEIGHT * $recency) + (self::CADENCE_WEIGHT * $cadence);

        return $this->result($normalized, [
            'days_since_last_commit' => $daysSinceLastCommit,
            'commits_last_90d' => $commitsLast90Days,
            'recency_component' => round($recency, 3),
            'cadence_component' => round($cadence, 3),
        ]);
    }
}
