<?php

declare(strict_types=1);

namespace Cpts\Score;

class ScoreResult
{
    /**
     * @param array<string, MetricResult> $metricResults
     */
    public function __construct(
        public readonly string $package,
        public readonly float $score,
        public readonly array $metricResults,
        public readonly float $trustBonus,
        public readonly float $rawTrustBonus,
        public readonly \DateTimeInterface $calculatedAt,
    ) {
    }

    public function getPackage(): string
    {
        return $this->package;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function getTrustBonus(): float
    {
        return $this->trustBonus;
    }

    public function getRawTrustBonus(): float
    {
        return $this->rawTrustBonus;
    }

    /**
     * @return array<string, MetricResult>
     */
    public function getMetricResults(): array
    {
        return $this->metricResults;
    }

    public function getMetricResult(string $name): ?MetricResult
    {
        return $this->metricResults[$name] ?? null;
    }

    public function getGrade(): string
    {
        return match (true) {
            $this->score >= 80 => 'A',
            $this->score >= 60 => 'B',
            $this->score >= 40 => 'C',
            $this->score >= 20 => 'D',
            default => 'F',
        };
    }

    public function getGradeEmoji(): string
    {
        return match ($this->getGrade()) {
            'A' => '⬜',  // white - excellent
            'B' => '🟡',  // yellow - good
            'C' => '🟠',  // orange - caution
            'D' => '🔴',  // red - warning
            'F' => '⚫',  // black - fail
            default => '❓',
        };
    }

    public function getGradeWithEmoji(): string
    {
        return $this->getGradeEmoji() . ' ' . $this->getGrade();
    }

    /**
     * Get metric breakdown as emoji string, only showing yellow/red metrics.
     */
    public function getMetricEmojis(): string
    {
        $parts = [];
        foreach ($this->metricResults as $result) {
            if (!$result->isGood()) {
                $parts[] = $result->getEmojiWithColor();
            }
        }

        return implode(' ', $parts);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'package' => $this->package,
            'score' => round($this->score, 1),
            'grade' => $this->getGrade(),
            'trust_bonus' => round($this->trustBonus, 2),
            'raw_trust_bonus' => round($this->rawTrustBonus, 2),
            'calculated_at' => $this->calculatedAt->format(\DateTimeInterface::ATOM),
            'metrics' => array_map(
                fn(MetricResult $r) => $r->toArray(),
                $this->metricResults
            ),
        ];
    }
}
