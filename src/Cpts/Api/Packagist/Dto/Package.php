<?php

declare(strict_types=1);

namespace Cpts\Api\Packagist\Dto;

class Package
{
    /**
     * @param Version[] $versions
     * @param string[] $maintainers
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $repository,
        public readonly int $downloads,
        public readonly int $favers,
        public readonly array $versions,
        public readonly array $maintainers,
        public readonly ?string $type,
        public readonly bool $abandoned,
        public readonly ?string $replacementPackage,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromApiResponse(array $data): self
    {
        $package = $data['package'] ?? $data;
        $versions = [];

        foreach ($package['versions'] ?? [] as $versionData) {
            $versions[] = Version::fromApiResponse($versionData);
        }

        $maintainers = [];
        foreach ($package['maintainers'] ?? [] as $maintainer) {
            $maintainers[] = $maintainer['name'] ?? $maintainer;
        }

        return new self(
            name: $package['name'] ?? '',
            description: $package['description'] ?? null,
            repository: $package['repository'] ?? null,
            downloads: (int) ($package['downloads']['total'] ?? $package['downloads'] ?? 0),
            favers: (int) ($package['favers'] ?? 0),
            versions: $versions,
            maintainers: $maintainers,
            type: $package['type'] ?? null,
            abandoned: !empty($package['abandoned']),
            replacementPackage: is_string($package['abandoned'] ?? null) ? $package['abandoned'] : null,
        );
    }

    public function getGitHubOwnerAndRepo(): ?array
    {
        if ($this->repository === null) {
            return null;
        }

        // Match github.com URLs
        if (preg_match('#github\.com[/:]([^/]+)/([^/\.]+)#', $this->repository, $matches)) {
            return [
                'owner' => $matches[1],
                'repo' => rtrim($matches[2], '.git'),
            ];
        }

        return null;
    }

    public function getLatestVersion(): ?Version
    {
        foreach ($this->versions as $version) {
            // Skip dev versions
            if (str_starts_with($version->version, 'dev-')) {
                continue;
            }

            return $version;
        }

        return $this->versions[0] ?? null;
    }

    public function getDirectDependencyCount(): int
    {
        $latest = $this->getLatestVersion();
        if ($latest === null) {
            return 0;
        }

        // Count require dependencies (excluding php and ext-*)
        return count(array_filter(
            array_keys($latest->require),
            fn(string $dep) => !str_starts_with($dep, 'php') && !str_starts_with($dep, 'ext-')
        ));
    }
}
