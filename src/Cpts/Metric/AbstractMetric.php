<?php

declare(strict_types=1);

namespace Cpts\Metric;

use Cpts\Config\ConfigInterface;
use Cpts\Package\PackageInfo;
use Cpts\Score\MetricResult;

abstract class AbstractMetric implements MetricInterface
{
    public function __construct(
        protected readonly ConfigInterface $config,
    ) {
    }

    abstract public function getName(): string;

    abstract public function getDescription(): string;

    abstract public function getDefaultWeight(): float;

    abstract public function getEmoji(): string;

    abstract public function calculate(PackageInfo $package): MetricResult;

    public function getWeight(): float
    {
        $customWeights = $this->config->getMetricWeights();

        return $customWeights[$this->getName()] ?? $this->getDefaultWeight();
    }

    public function isApplicable(PackageInfo $package): bool
    {
        return true;
    }

    public function isHigherBetter(): bool
    {
        return true;
    }

    /**
     * Clamp a value between 0 and 1.
     */
    protected function clamp(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }

    /**
     * Create a successful metric result.
     *
     * @param array<string, mixed> $rawValue
     */
    protected function result(float $normalized, array $rawValue = []): MetricResult
    {
        return new MetricResult(
            name: $this->getName(),
            normalizedScore: $this->clamp($normalized),
            rawValue: $rawValue,
            weight: $this->getWeight(),
            emoji: $this->getEmoji(),
        );
    }
}
