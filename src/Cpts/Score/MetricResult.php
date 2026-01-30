<?php

declare(strict_types=1);

namespace Cpts\Score;

class MetricResult
{
    /**
     * @param array<string, mixed> $rawValue
     */
    public function __construct(
        public readonly string $name,
        public readonly float $normalizedScore,
        public readonly array $rawValue,
        public readonly float $weight,
        public readonly string $emoji = '❓',
        public readonly bool $failed = false,
        public readonly ?string $error = null,
    ) {
    }

    public static function failed(string $name, string $error): self
    {
        return new self(
            name: $name,
            normalizedScore: 0.0,
            rawValue: [],
            weight: 0.0,
            emoji: '❓',
            failed: true,
            error: $error,
        );
    }

    public function getEmojiWithColor(): string
    {
        return $this->emoji . $this->getColorDot();
    }

    public function getNormalizedScore(): float
    {
        return $this->normalizedScore;
    }

    public function getWeightedScore(): float
    {
        return $this->normalizedScore * $this->weight;
    }

    public function getColorDot(): string
    {
        if ($this->failed) {
            return '⚫';
        }

        return match (true) {
            $this->normalizedScore >= 0.8 => '🟢',  // green - excellent
            $this->normalizedScore >= 0.6 => '🟡',  // yellow - good
            $this->normalizedScore >= 0.4 => '🟠',  // orange - caution
            $this->normalizedScore >= 0.2 => '🔴',  // red - warning
            default => '⚫',                         // black - fail
        };
    }

    public function isGood(): bool
    {
        return !$this->failed && $this->normalizedScore >= 0.8;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'normalized_score' => round($this->normalizedScore, 3),
            'weight' => $this->weight,
            'weighted_score' => round($this->getWeightedScore(), 3),
            'raw' => $this->rawValue,
            'failed' => $this->failed,
            'error' => $this->error,
        ];
    }
}
