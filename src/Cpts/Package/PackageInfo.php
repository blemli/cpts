<?php

declare(strict_types=1);

namespace Cpts\Package;

use Cpts\Api\GitHub\Dto\Commit;
use Cpts\Api\GitHub\Dto\Issue;
use Cpts\Api\GitHub\Dto\PullRequest;
use Cpts\Api\GitHub\Dto\Repository;
use Cpts\Api\Packagist\Dto\Package;
use Cpts\Api\Packagist\Dto\Stats;

/**
 * Aggregated package information from GitHub and Packagist.
 */
class PackageInfo
{
    /** @var Commit[] */
    private array $commits = [];

    /** @var Issue[] */
    private array $issues = [];

    /** @var PullRequest[] */
    private array $pullRequests = [];

    /** @var string[] */
    private array $detectedAiArtifacts = [];

    private ?string $readmeContent = null;
    private ?\DateTimeInterface $firstCommitDate = null;
    private int $testFileCount = 0;
    private int $sourceFileCount = 0;
    private int $todoCount = 0;
    private int $linesOfCode = 0;
    private int $stubCount = 0;
    private int $maintainerReputationScore = 0;

    public function __construct(
        private readonly string $name,
        private readonly ?Repository $repository = null,
        private readonly ?Package $packagistPackage = null,
        private readonly ?Stats $packagistStats = null,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    // ========== Data availability checks ==========

    public function hasGitHubData(): bool
    {
        return $this->repository !== null;
    }

    public function hasPackagistData(): bool
    {
        return $this->packagistPackage !== null;
    }

    // ========== GitHub repository data ==========

    public function getRepository(): ?Repository
    {
        return $this->repository;
    }

    public function getStarsCount(): int
    {
        return $this->repository?->starsCount ?? 0;
    }

    public function getOpenIssueCount(): int
    {
        return $this->repository?->openIssuesCount ?? 0;
    }

    public function isVerifiedOrganization(): bool
    {
        return $this->repository?->isVerifiedOrganization ?? false;
    }

    public function getAgeInYears(): float
    {
        return $this->repository?->getAgeInYears() ?? 0.0;
    }

    public function getFirstCommitDate(): ?\DateTimeInterface
    {
        return $this->firstCommitDate ?? $this->repository?->createdAt;
    }

    public function setFirstCommitDate(?\DateTimeInterface $date): void
    {
        $this->firstCommitDate = $date;
    }

    // ========== Commit data ==========

    /**
     * @param Commit[] $commits
     */
    public function setCommits(array $commits): void
    {
        $this->commits = $commits;
    }

    /**
     * @return Commit[]
     */
    public function getRecentCommits(): array
    {
        return $this->commits;
    }

    public function getDaysSinceLastCommit(): int
    {
        if (empty($this->commits)) {
            if ($this->repository?->pushedAt !== null) {
                $diff = (new \DateTimeImmutable())->diff($this->repository->pushedAt);

                return $diff->days;
            }

            return 365;
        }

        $lastCommit = $this->commits[0];
        $diff = (new \DateTimeImmutable())->diff($lastCommit->authoredAt);

        return $diff->days;
    }

    public function getCommitsLast90Days(): int
    {
        $cutoff = new \DateTimeImmutable('-90 days');
        $count = 0;

        foreach ($this->commits as $commit) {
            if ($commit->authoredAt >= $cutoff) {
                $count++;
            }
        }

        return $count;
    }

    public function getUniqueCommittersLast180Days(): int
    {
        $cutoff = new \DateTimeImmutable('-180 days');
        $authors = [];

        foreach ($this->commits as $commit) {
            if ($commit->authoredAt >= $cutoff) {
                $key = $commit->authorLogin ?? $commit->authorEmail ?? $commit->authorName;
                $authors[$key] = true;
            }
        }

        return count($authors);
    }

    // ========== Issue data ==========

    /**
     * @param Issue[] $issues
     */
    public function setIssues(array $issues): void
    {
        $this->issues = $issues;
    }

    /**
     * @param PullRequest[] $pullRequests
     */
    public function setPullRequests(array $pullRequests): void
    {
        $this->pullRequests = $pullRequests;
    }

    public function getIssuesOpenedLast365Days(): int
    {
        $cutoff = new \DateTimeImmutable('-365 days');

        return count(array_filter(
            $this->issues,
            fn(Issue $i) => !$i->isPullRequest && $i->createdAt >= $cutoff
        ));
    }

    public function getIssuesClosedLast365Days(): int
    {
        $cutoff = new \DateTimeImmutable('-365 days');

        return count(array_filter(
            $this->issues,
            fn(Issue $i) => !$i->isPullRequest && $i->isClosed() && $i->closedAt >= $cutoff
        ));
    }

    public function getMedianFirstResponseDays(): float
    {
        // Simplified: use comment count as proxy for response time
        // Real implementation would check first comment timestamp
        $responseTimes = [];

        foreach ($this->issues as $issue) {
            if ($issue->isPullRequest) {
                continue;
            }

            // If has comments, assume some response happened
            // Use time to close as rough proxy if closed
            if ($issue->closedAt !== null) {
                $days = $issue->getTimeToCloseInDays();
                if ($days !== null) {
                    $responseTimes[] = min($days, 30); // Cap at 30 days
                }
            } elseif ($issue->comments > 0) {
                // Has comments but not closed - assume quick response
                $responseTimes[] = 3;
            }
        }

        if (empty($responseTimes)) {
            return 7; // Default to 7 days if no data
        }

        sort($responseTimes);
        $count = count($responseTimes);
        $mid = (int) floor($count / 2);

        if ($count % 2 === 0) {
            return ($responseTimes[$mid - 1] + $responseTimes[$mid]) / 2;
        }

        return $responseTimes[$mid];
    }

    public function getAverageReviewCommentsPerPr(): float
    {
        if (empty($this->pullRequests)) {
            return 0;
        }

        $total = array_sum(array_map(
            fn(PullRequest $pr) => $pr->getTotalReviewComments(),
            $this->pullRequests
        ));

        return $total / count($this->pullRequests);
    }

    // ========== Packagist data ==========

    public function getDependentsCount(): int
    {
        return $this->packagistStats?->dependents ?? 0;
    }

    public function getDirectDependencyCount(): int
    {
        return $this->packagistPackage?->getDirectDependencyCount() ?? 0;
    }

    public function isAbandoned(): bool
    {
        return $this->packagistPackage?->abandoned ?? false;
    }

    // ========== AIRS data ==========

    /**
     * @param string[] $artifacts
     */
    public function setDetectedAiArtifacts(array $artifacts): void
    {
        $this->detectedAiArtifacts = $artifacts;
    }

    /**
     * @return string[]
     */
    public function getDetectedAiArtifacts(): array
    {
        return $this->detectedAiArtifacts;
    }

    public function setReadmeContent(?string $content): void
    {
        $this->readmeContent = $content;
    }

    public function getReadmeContent(): ?string
    {
        return $this->readmeContent;
    }

    // ========== Hygiene data ==========

    public function setTestFileCount(int $count): void
    {
        $this->testFileCount = $count;
    }

    public function getTestFileCount(): int
    {
        return $this->testFileCount;
    }

    public function setSourceFileCount(int $count): void
    {
        $this->sourceFileCount = $count;
    }

    public function getSourceFileCount(): int
    {
        return $this->sourceFileCount;
    }

    public function setTodoCount(int $count): void
    {
        $this->todoCount = $count;
    }

    public function getTodoCount(): int
    {
        return $this->todoCount;
    }

    public function setLinesOfCode(int $loc): void
    {
        $this->linesOfCode = $loc;
    }

    public function getLinesOfCode(): int
    {
        return $this->linesOfCode;
    }

    public function setStubCount(int $count): void
    {
        $this->stubCount = $count;
    }

    public function getStubCount(): int
    {
        return $this->stubCount;
    }

    // ========== Trust bonus data ==========

    public function setMaintainerReputationScore(int $score): void
    {
        $this->maintainerReputationScore = $score;
    }

    public function getMaintainerReputationScore(): int
    {
        return $this->maintainerReputationScore;
    }
}
