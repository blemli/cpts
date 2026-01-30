<?php

declare(strict_types=1);

namespace Cpts\Api\GitHub\Dto;

class Issue
{
    public function __construct(
        public readonly int $number,
        public readonly string $title,
        public readonly string $state,
        public readonly \DateTimeInterface $createdAt,
        public readonly ?\DateTimeInterface $closedAt,
        public readonly ?string $authorLogin,
        public readonly int $comments,
        public readonly bool $isPullRequest,
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
            closedAt: isset($data['closed_at']) ? new \DateTimeImmutable($data['closed_at']) : null,
            authorLogin: $data['user']['login'] ?? null,
            comments: (int) ($data['comments'] ?? 0),
            isPullRequest: isset($data['pull_request']),
        );
    }

    public function isOpen(): bool
    {
        return $this->state === 'open';
    }

    public function isClosed(): bool
    {
        return $this->state === 'closed';
    }

    public function getTimeToCloseInDays(): ?float
    {
        if ($this->closedAt === null) {
            return null;
        }

        $diff = $this->closedAt->diff($this->createdAt);

        return $diff->days + ($diff->h / 24) + ($diff->i / 1440);
    }
}
