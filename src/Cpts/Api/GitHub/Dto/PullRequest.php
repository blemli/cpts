<?php

declare(strict_types=1);

namespace Cpts\Api\GitHub\Dto;

class PullRequest
{
    public function __construct(
        public readonly int $number,
        public readonly string $title,
        public readonly string $state,
        public readonly \DateTimeInterface $createdAt,
        public readonly ?\DateTimeInterface $mergedAt,
        public readonly ?\DateTimeInterface $closedAt,
        public readonly ?string $authorLogin,
        public readonly int $comments,
        public readonly int $reviewComments,
        public readonly int $additions,
        public readonly int $deletions,
        public readonly int $changedFiles,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            number: (int) ($data['number'] ?? 0),
            title: $data['title'] ?? '',
            state: $data['state'] ?? 'open',
            createdAt: new \DateTimeImmutable($data['created_at'] ?? 'now'),
            mergedAt: isset($data['merged_at']) ? new \DateTimeImmutable($data['merged_at']) : null,
            closedAt: isset($data['closed_at']) ? new \DateTimeImmutable($data['closed_at']) : null,
            authorLogin: $data['user']['login'] ?? null,
            comments: (int) ($data['comments'] ?? 0),
            reviewComments: (int) ($data['review_comments'] ?? 0),
            additions: (int) ($data['additions'] ?? 0),
            deletions: (int) ($data['deletions'] ?? 0),
            changedFiles: (int) ($data['changed_files'] ?? 0),
        );
    }

    public function isMerged(): bool
    {
        return $this->mergedAt !== null;
    }

    public function getTotalReviewComments(): int
    {
        return $this->comments + $this->reviewComments;
    }
}
