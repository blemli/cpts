<?php

declare(strict_types=1);

namespace Cpts\Api\Packagist\Dto;

class Stats
{
    public function __construct(
        public readonly int $totalDownloads,
        public readonly int $monthlyDownloads,
        public readonly int $dailyDownloads,
        public readonly int $dependents,
        public readonly int $suggesters,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromApiResponse(array $data): self
    {
        $package = $data['package'] ?? $data;
        $downloads = $package['downloads'] ?? [];

        return new self(
            totalDownloads: (int) ($downloads['total'] ?? 0),
            monthlyDownloads: (int) ($downloads['monthly'] ?? 0),
            dailyDownloads: (int) ($downloads['daily'] ?? 0),
            dependents: (int) ($package['dependents'] ?? 0),
            suggesters: (int) ($package['suggesters'] ?? 0),
        );
    }
}
