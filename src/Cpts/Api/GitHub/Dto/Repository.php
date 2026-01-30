<?php

declare(strict_types=1);

namespace Cpts\Api\GitHub\Dto;

class Repository
{
    public function __construct(
        public readonly string $owner,
        public readonly string $name,
        public readonly string $fullName,
        public readonly ?string $description,
        public readonly int $starsCount,
        public readonly int $forksCount,
        public readonly int $openIssuesCount,
        public readonly bool $hasIssues,
        public readonly bool $archived,
        public readonly bool $disabled,
        public readonly \DateTimeInterface $createdAt,
        public readonly \DateTimeInterface $updatedAt,
        public readonly ?\DateTimeInterface $pushedAt,
        public readonly ?string $defaultBranch,
        public readonly bool $isOrganization,
        public readonly bool $isVerifiedOrganization,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromApiResponse(array $data): self
    {
        $owner = $data['owner'] ?? [];

        return new self(
            owner: $owner['login'] ?? '',
            name: $data['name'] ?? '',
            fullName: $data['full_name'] ?? '',
            description: $data['description'] ?? null,
            starsCount: (int) ($data['stargazers_count'] ?? 0),
            forksCount: (int) ($data['forks_count'] ?? 0),
            openIssuesCount: (int) ($data['open_issues_count'] ?? 0),
            hasIssues: (bool) ($data['has_issues'] ?? true),
            archived: (bool) ($data['archived'] ?? false),
            disabled: (bool) ($data['disabled'] ?? false),
            createdAt: new \DateTimeImmutable($data['created_at'] ?? 'now'),
            updatedAt: new \DateTimeImmutable($data['updated_at'] ?? 'now'),
            pushedAt: isset($data['pushed_at']) ? new \DateTimeImmutable($data['pushed_at']) : null,
            defaultBranch: $data['default_branch'] ?? 'main',
            isOrganization: ($owner['type'] ?? '') === 'Organization',
            isVerifiedOrganization: (bool) ($owner['is_verified'] ?? false),
        );
    }

    public function getAgeInYears(): float
    {
        $now = new \DateTimeImmutable();
        $diff = $now->diff($this->createdAt);

        return $diff->days / 365.25;
    }
}
