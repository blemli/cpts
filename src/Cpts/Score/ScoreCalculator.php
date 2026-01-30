<?php

declare(strict_types=1);

namespace Cpts\Score;

use Cpts\Api\Exception\RateLimitException;
use Cpts\Metric\MetricRegistry;
use Cpts\Package\PackageInfo;

class ScoreCalculator
{
    private const WEIGHT_DIVISOR = 21;
    private const TRUST_BONUS_MULTIPLIER = 10;
    private const TRUST_BONUS_MIN = -1.0;
    private const TRUST_BONUS_MAX = 1.0;
    private const SCORE_MIN = 0.0;
    private const SCORE_MAX = 100.0;

    public function __construct(
        private readonly MetricRegistry $metricRegistry,
        private readonly TrustBonus $trustBonus,
    ) {
    }

    public function calculate(PackageInfo $package): ScoreResult
    {
        $metricResults = [];
        $weightedSum = 0.0;

        foreach ($this->metricRegistry->getMetrics() as $metric) {
            if (!$metric->isApplicable($package)) {
                continue;
            }

            try {
                $result = $metric->calculate($package);
                $metricResults[$metric->getName()] = $result;
                $weightedSum += $result->getWeightedScore();
            } catch (RateLimitException $e) {
                throw $e; // Bubble up rate limit errors
            } catch (\Exception $e) {
                // Log and continue with degraded scoring
                $metricResults[$metric->getName()] = MetricResult::failed(
                    $metric->getName(),
                    $e->getMessage()
                );
            }
        }

        // Calculate trust bonus
        $trustBonusValue = $this->trustBonus->calculate($package);
        $clampedBonus = max(self::TRUST_BONUS_MIN, min(self::TRUST_BONUS_MAX, $trustBonusValue));

        // Final score formula: 100 * (weighted_sum / 21) + 10 * trust_bonus
        $baseScore = 100 * ($weightedSum / self::WEIGHT_DIVISOR);
        $finalScore = $baseScore + (self::TRUST_BONUS_MULTIPLIER * $clampedBonus);

        // Clamp final score to 0-100
        $finalScore = max(self::SCORE_MIN, min(self::SCORE_MAX, $finalScore));

        return new ScoreResult(
            package: $package->getName(),
            score: $finalScore,
            metricResults: $metricResults,
            trustBonus: $clampedBonus,
            rawTrustBonus: $trustBonusValue,
            calculatedAt: new \DateTimeImmutable(),
        );
    }
}
