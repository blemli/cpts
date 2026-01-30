<?php

declare(strict_types=1);

namespace Cpts\Package;

use Cpts\Api\GitHub\GitHubClientInterface;
use Cpts\Api\Packagist\PackagistClientInterface;
use Cpts\Exception\PackageNotFoundException;

/**
 * Resolves package names to PackageInfo with all data fetched.
 */
class PackageResolver
{
    // AI artifact patterns to detect
    private const AI_ARTIFACTS = [
        'claude.md',
        'CLAUDE.md',
        '.claude',
        'gpt.md',
        'GPT.md',
        'prompt.md',
        'PROMPT.md',
        'system.md',
        'SYSTEM.md',
        '.cursorrules',
        '.cursor',
        '.continue',
        '.github/copilot-instructions.md',
        '.prompts',
        '.ai',
        '.llm',
    ];

    public function __construct(
        private readonly GitHubClientInterface $gitHub,
        private readonly PackagistClientInterface $packagist,
    ) {
    }

    /**
     * Resolve a package name to full PackageInfo.
     */
    public function resolve(string $packageName): PackageInfo
    {
        [$vendor, $name] = $this->parsePackageName($packageName);

        // Fetch Packagist data
        $packagistPackage = null;
        $packagistStats = null;

        try {
            $packagistPackage = $this->packagist->getPackage($vendor, $name);
            $packagistStats = $this->packagist->getStats($vendor, $name);
        } catch (\Exception) {
            // Packagist data optional
        }

        // Determine GitHub repo
        $repository = null;
        $gitHubInfo = $packagistPackage?->getGitHubOwnerAndRepo();

        if ($gitHubInfo !== null) {
            try {
                $repository = $this->gitHub->getRepository($gitHubInfo['owner'], $gitHubInfo['repo']);
            } catch (\Exception) {
                // GitHub data optional
            }
        }

        $packageInfo = new PackageInfo(
            $packageName,
            $repository,
            $packagistPackage,
            $packagistStats,
        );

        // Fetch additional data if we have GitHub access
        if ($gitHubInfo !== null && $repository !== null) {
            $this->fetchGitHubData($packageInfo, $gitHubInfo['owner'], $gitHubInfo['repo']);
        }

        return $packageInfo;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parsePackageName(string $packageName): array
    {
        $parts = explode('/', $packageName, 2);

        if (count($parts) !== 2) {
            throw new PackageNotFoundException($packageName, 'Invalid package name format');
        }

        return [$parts[0], $parts[1]];
    }

    private function fetchGitHubData(PackageInfo $package, string $owner, string $repo): void
    {
        // Fetch commits (last 180 days worth)
        try {
            $since = new \DateTimeImmutable('-180 days');
            $commits = $this->gitHub->getCommits($owner, $repo, $since);
            $package->setCommits($commits);
        } catch (\Exception) {
            // Continue without commit data
        }

        // Fetch issues
        try {
            $since = new \DateTimeImmutable('-365 days');
            $issues = $this->gitHub->getIssues($owner, $repo, 'all', $since);
            $package->setIssues($issues);
        } catch (\Exception) {
            // Continue without issue data
        }

        // Fetch pull requests
        try {
            $pullRequests = $this->gitHub->getPullRequests($owner, $repo, 'all');
            $package->setPullRequests($pullRequests);
        } catch (\Exception) {
            // Continue without PR data
        }

        // Fetch first commit date
        try {
            $firstCommitDate = $this->gitHub->getFirstCommitDate($owner, $repo);
            $package->setFirstCommitDate($firstCommitDate);
        } catch (\Exception) {
            // Use repo creation date as fallback
        }

        // Detect AI artifacts
        $this->detectAiArtifacts($package, $owner, $repo);

        // Fetch README
        $this->fetchReadme($package, $owner, $repo);

        // Fetch hygiene metrics (simplified - would need more API calls for full implementation)
        $this->fetchHygieneData($package, $owner, $repo);
    }

    private function detectAiArtifacts(PackageInfo $package, string $owner, string $repo): void
    {
        $detected = [];

        try {
            $contents = $this->gitHub->getRepositoryContents($owner, $repo);

            foreach ($contents as $file) {
                $name = $file->name;
                $path = $file->path;

                foreach (self::AI_ARTIFACTS as $artifact) {
                    if ($name === $artifact || $path === $artifact || str_starts_with($path, $artifact . '/')) {
                        $detected[] = $path;
                        break;
                    }
                }
            }

            // Check .github directory for copilot instructions
            $githubContents = $this->gitHub->getRepositoryContents($owner, $repo, '.github');
            foreach ($githubContents as $file) {
                if ($file->name === 'copilot-instructions.md') {
                    $detected[] = '.github/copilot-instructions.md';
                }
            }
        } catch (\Exception) {
            // Continue without AI artifact detection
        }

        $package->setDetectedAiArtifacts(array_unique($detected));
    }

    private function fetchReadme(PackageInfo $package, string $owner, string $repo): void
    {
        $readmeNames = ['README.md', 'Readme.md', 'readme.md', 'README'];

        foreach ($readmeNames as $name) {
            try {
                $file = $this->gitHub->getFileContent($owner, $repo, $name);
                if ($file !== null) {
                    $package->setReadmeContent($file->getDecodedContent());

                    return;
                }
            } catch (\Exception) {
                continue;
            }
        }
    }

    private function fetchHygieneData(PackageInfo $package, string $owner, string $repo): void
    {
        try {
            $contents = $this->gitHub->getRepositoryContents($owner, $repo);

            $srcCount = 0;
            $testCount = 0;

            foreach ($contents as $file) {
                if ($file->isDirectory()) {
                    $name = strtolower($file->name);

                    if (in_array($name, ['src', 'lib', 'app'], true)) {
                        // Would need recursive count - simplified here
                        $srcCount = 10; // Placeholder
                    }

                    if (in_array($name, ['tests', 'test', 'spec'], true)) {
                        $testCount = 5; // Placeholder
                    }
                }
            }

            $package->setSourceFileCount($srcCount);
            $package->setTestFileCount($testCount);
            $package->setLinesOfCode(1000); // Placeholder
            $package->setTodoCount(0); // Would need file content search
            $package->setStubCount(0); // Would need file content search
        } catch (\Exception) {
            // Use defaults
        }
    }
}
