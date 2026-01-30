<?php

declare(strict_types=1);

namespace Cpts\Api\GitHub;

use Cpts\Api\GitHub\Dto\Commit;
use Cpts\Api\GitHub\Dto\Contributor;
use Cpts\Api\GitHub\Dto\FileContent;
use Cpts\Api\GitHub\Dto\Issue;
use Cpts\Api\GitHub\Dto\PullRequest;
use Cpts\Api\GitHub\Dto\Repository;

interface GitHubClientInterface
{
    public function getRepository(string $owner, string $repo): Repository;

    /**
     * @return Commit[]
     */
    public function getCommits(string $owner, string $repo, ?\DateTimeInterface $since = null, int $perPage = 100): array;

    /**
     * @return Contributor[]
     */
    public function getContributors(string $owner, string $repo): array;

    /**
     * @return Issue[]
     */
    public function getIssues(string $owner, string $repo, string $state = 'all', ?\DateTimeInterface $since = null): array;

    /**
     * @return PullRequest[]
     */
    public function getPullRequests(string $owner, string $repo, string $state = 'all'): array;

    public function getFirstCommitDate(string $owner, string $repo): ?\DateTimeInterface;

    /**
     * @return FileContent[]
     */
    public function getRepositoryContents(string $owner, string $repo, string $path = ''): array;

    public function getFileContent(string $owner, string $repo, string $path): ?FileContent;

    public function getRemainingRateLimit(): int;

    public function isAuthenticated(): bool;
}
