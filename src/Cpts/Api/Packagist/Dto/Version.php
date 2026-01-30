<?php

declare(strict_types=1);

namespace Cpts\Api\Packagist\Dto;

class Version
{
    /**
     * @param array<string, string> $require
     * @param array<string, string> $requireDev
     * @param string[] $keywords
     */
    public function __construct(
        public readonly string $version,
        public readonly string $versionNormalized,
        public readonly ?string $license,
        public readonly array $require,
        public readonly array $requireDev,
        public readonly \DateTimeInterface $time,
        public readonly array $keywords,
        public readonly ?string $source,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            version: $data['version'] ?? '',
            versionNormalized: $data['version_normalized'] ?? $data['version'] ?? '',
            license: is_array($data['license'] ?? null) ? ($data['license'][0] ?? null) : ($data['license'] ?? null),
            require: $data['require'] ?? [],
            requireDev: $data['require-dev'] ?? [],
            time: new \DateTimeImmutable($data['time'] ?? 'now'),
            keywords: $data['keywords'] ?? [],
            source: $data['source']['url'] ?? null,
        );
    }

    public function isDev(): bool
    {
        return str_starts_with($this->version, 'dev-');
    }

    public function isStable(): bool
    {
        return !$this->isDev()
            && !str_contains($this->version, 'alpha')
            && !str_contains($this->version, 'beta')
            && !str_contains($this->version, 'rc');
    }
}
