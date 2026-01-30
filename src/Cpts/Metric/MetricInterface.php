<?php

declare(strict_types=1);

namespace Cpts\Metric;

use Cpts\Package\PackageInfo;
use Cpts\Score\MetricResult;

interface MetricInterface
{
    /**
     * Unique identifier for this metric.
     */
    public function getName(): string;

    /**
     * Human-readable description.
     */
    public function getDescription(): string;

    /**
     * Weight in the scoring formula.
     */
    public function getWeight(): float;

    /**
     * Calculate the normalized score (0.0 to 1.0).
     *
     * @throws \Cpts\Exception\ScoreCalculationException
     */
    public function calculate(PackageInfo $package): MetricResult;

    /**
     * Whether this metric can be calculated with available data.
     */
    public function isApplicable(PackageInfo $package): bool;

    /**
     * Whether higher raw values mean better scores.
     * Default true; set false for metrics like "dependency count".
     */
    public function isHigherBetter(): bool;

    /**
     * Emoji icon for this metric.
     */
    public function getEmoji(): string;
}
