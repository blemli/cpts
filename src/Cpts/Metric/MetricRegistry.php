<?php

declare(strict_types=1);

namespace Cpts\Metric;

use Cpts\Config\ConfigInterface;

class MetricRegistry
{
    /** @var MetricInterface[] */
    private array $metrics = [];

    public function __construct(
        private readonly ConfigInterface $config,
    ) {
        $this->registerDefaultMetrics();
    }

    private function registerDefaultMetrics(): void
    {
        $this->register(new AirsMetric($this->config));
        $this->register(new ActivityMetric($this->config));
        $this->register(new CommittersMetric($this->config));
        $this->register(new StarsMetric($this->config));
        $this->register(new DependentsMetric($this->config));
        $this->register(new RepoAgeMetric($this->config));
        $this->register(new HygieneMetric($this->config));
        $this->register(new IssueBehaviourMetric($this->config));
        $this->register(new DependencyCountMetric($this->config));
    }

    public function register(MetricInterface $metric): void
    {
        $this->metrics[$metric->getName()] = $metric;
    }

    public function get(string $name): ?MetricInterface
    {
        return $this->metrics[$name] ?? null;
    }

    /**
     * @return MetricInterface[]
     */
    public function getMetrics(): array
    {
        return array_values($this->metrics);
    }

    /**
     * Get total weight of all registered metrics.
     */
    public function getTotalWeight(): float
    {
        return array_sum(array_map(
            fn(MetricInterface $m) => $m->getWeight(),
            $this->metrics
        ));
    }
}
