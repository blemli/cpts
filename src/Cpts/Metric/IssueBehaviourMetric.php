<?php

declare(strict_types=1);

namespace Cpts\Metric;

use Cpts\Package\PackageInfo;
use Cpts\Score\MetricResult;

/**
 * Issue Behaviour Metric
 *
 * Maintainer responsiveness:
 * - close_ratio = closed / (opened + 1) over 365d
 * - response_norm = 1 - min(median_first_response_days / 14, 1)
 * - review_norm = min(review_comments_per_pr / 3, 1)
 *
 * issues_norm = 0.4*close_ratio + 0.3*response_norm + 0.3*review_norm
 */
class IssueBehaviourMetric extends AbstractMetric
{
    private const RESPONSE_MAX_DAYS = 14;
    private const REVIEW_COMMENTS_TARGET = 3;

    private const WEIGHT_CLOSE = 0.4;
    private const WEIGHT_RESPONSE = 0.3;
    private const WEIGHT_REVIEW = 0.3;

    public function getName(): string
    {
        return 'issue_behaviour';
    }

    public function getDescription(): string
    {
        return 'Issue/PR responsiveness';
    }

    public function getDefaultWeight(): float
    {
        return 4.0;
    }

    public function isApplicable(PackageInfo $package): bool
    {
        return $package->hasGitHubData();
    }

    public function calculate(PackageInfo $package): MetricResult
    {
        $opened = $package->getIssuesOpenedLast365Days();
        $closed = $package->getIssuesClosedLast365Days();
        $medianResponseDays = $package->getMedianFirstResponseDays();
        $reviewCommentsPerPr = $package->getAverageReviewCommentsPerPr();

        // close_ratio = closed / (opened + 1)
        $closeRatio = $closed / ($opened + 1);
        $closeNorm = min($closeRatio, 1.0);

        // response_norm = 1 - min(median_response/14, 1)
        $responseNorm = 1 - min($medianResponseDays / self::RESPONSE_MAX_DAYS, 1.0);

        // review_norm = min(comments_per_pr/3, 1)
        $reviewNorm = min($reviewCommentsPerPr / self::REVIEW_COMMENTS_TARGET, 1.0);

        // issues_norm = 0.4*close + 0.3*response + 0.3*review
        $normalized = (self::WEIGHT_CLOSE * $closeNorm)
            + (self::WEIGHT_RESPONSE * $responseNorm)
            + (self::WEIGHT_REVIEW * $reviewNorm);

        return $this->result($normalized, [
            'issues_opened_365d' => $opened,
            'issues_closed_365d' => $closed,
            'close_ratio' => round($closeRatio, 3),
            'close_norm' => round($closeNorm, 3),
            'median_response_days' => round($medianResponseDays, 1),
            'response_norm' => round($responseNorm, 3),
            'review_comments_per_pr' => round($reviewCommentsPerPr, 2),
            'review_norm' => round($reviewNorm, 3),
        ]);
    }
}
