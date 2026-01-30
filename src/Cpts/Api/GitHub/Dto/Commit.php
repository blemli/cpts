<?php

declare(strict_types=1);

namespace Cpts\Api\GitHub\Dto;

class Commit
{
    public function __construct(
        public readonly string $sha,
        public readonly string $message,
        public readonly string $authorName,
        public readonly ?string $authorEmail,
        public readonly ?string $authorLogin,
        public readonly \DateTimeInterface $authoredAt,
        public readonly bool $isSigned,
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
        $commit = $data['commit'] ?? [];
        $author = $commit['author'] ?? [];
        $verification = $commit['verification'] ?? [];
        $stats = $data['stats'] ?? [];

        return new self(
            sha: $data['sha'] ?? '',
            message: $commit['message'] ?? '',
            authorName: $author['name'] ?? 'Unknown',
            authorEmail: $author['email'] ?? null,
            authorLogin: $data['author']['login'] ?? null,
            authoredAt: new \DateTimeImmutable($author['date'] ?? 'now'),
            isSigned: (bool) ($verification['verified'] ?? false),
            additions: (int) ($stats['additions'] ?? 0),
            deletions: (int) ($stats['deletions'] ?? 0),
            changedFiles: (int) ($data['files'] ?? 0),
        );
    }

    /**
     * @param array<string, mixed> $data Simplified commit from list endpoint
     */
    public static function fromListResponse(array $data): self
    {
        $commit = $data['commit'] ?? [];
        $author = $commit['author'] ?? [];
        $verification = $commit['verification'] ?? [];

        return new self(
            sha: $data['sha'] ?? '',
            message: $commit['message'] ?? '',
            authorName: $author['name'] ?? 'Unknown',
            authorEmail: $author['email'] ?? null,
            authorLogin: $data['author']['login'] ?? null,
            authoredAt: new \DateTimeImmutable($author['date'] ?? 'now'),
            isSigned: (bool) ($verification['verified'] ?? false),
            additions: 0,
            deletions: 0,
            changedFiles: 0,
        );
    }

    public function getMessageLength(): int
    {
        return strlen($this->message);
    }

    public function isGenericMessage(): bool
    {
        $generic = [
            'update',
            'fix',
            'add',
            'remove',
            'change',
            'modify',
            'edit',
            'initial commit',
            'wip',
            'work in progress',
            'minor',
            'misc',
            'stuff',
        ];

        $normalized = strtolower(trim($this->message));

        foreach ($generic as $pattern) {
            if ($normalized === $pattern || str_starts_with($normalized, $pattern . ' ')) {
                return true;
            }
        }

        return false;
    }
}
