<?php

declare(strict_types=1);

namespace Cpts\Api\GitHub;

use Cpts\Api\Exception\ApiException;
use Cpts\Api\Exception\AuthenticationException;
use Cpts\Api\Exception\NetworkException;
use Cpts\Api\Exception\RateLimitException;
use Cpts\Api\GitHub\Dto\Commit;
use Cpts\Api\GitHub\Dto\Contributor;
use Cpts\Api\GitHub\Dto\FileContent;
use Cpts\Api\GitHub\Dto\Issue;
use Cpts\Api\GitHub\Dto\PullRequest;
use Cpts\Api\GitHub\Dto\Repository;
use Cpts\Cache\CacheInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

class GitHubClient implements GitHubClientInterface
{
    private const BASE_URL = 'https://api.github.com';
    private const CACHE_TTL_REPO = 86400;      // 24 hours
    private const CACHE_TTL_COMMITS = 14400;   // 4 hours
    private const CACHE_TTL_ISSUES = 14400;    // 4 hours
    private const CACHE_TTL_CONTRIBUTORS = 86400; // 24 hours
    private const CACHE_TTL_CONTENTS = 86400;  // 24 hours

    private int $remainingRateLimit = 5000;

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly ?string $token = null,
    ) {
    }

    public function getRepository(string $owner, string $repo): Repository
    {
        $cacheKey = "github.{$owner}.{$repo}.repository";
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $data = $this->request('GET', "/repos/{$owner}/{$repo}");
        $repository = Repository::fromApiResponse($data);

        $this->cache->set($cacheKey, $repository, self::CACHE_TTL_REPO);

        return $repository;
    }

    public function getCommits(string $owner, string $repo, ?\DateTimeInterface $since = null, int $perPage = 100): array
    {
        $sinceStr = $since?->format('Y-m-d');
        $cacheKey = "github.{$owner}.{$repo}.commits." . ($sinceStr ?? 'all');
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $params = ['per_page' => $perPage];
        if ($since !== null) {
            $params['since'] = $since->format(\DateTimeInterface::ATOM);
        }

        $data = $this->request('GET', "/repos/{$owner}/{$repo}/commits", $params);
        $commits = array_map(
            fn(array $item) => Commit::fromListResponse($item),
            $data
        );

        $this->cache->set($cacheKey, $commits, self::CACHE_TTL_COMMITS);

        return $commits;
    }

    public function getContributors(string $owner, string $repo): array
    {
        $cacheKey = "github.{$owner}.{$repo}.contributors";
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $data = $this->request('GET', "/repos/{$owner}/{$repo}/contributors", ['per_page' => 100]);
        $contributors = array_map(
            fn(array $item) => Contributor::fromApiResponse($item),
            $data
        );

        $this->cache->set($cacheKey, $contributors, self::CACHE_TTL_CONTRIBUTORS);

        return $contributors;
    }

    public function getIssues(string $owner, string $repo, string $state = 'all', ?\DateTimeInterface $since = null): array
    {
        $sinceStr = $since?->format('Y-m-d');
        $cacheKey = "github.{$owner}.{$repo}.issues.{$state}." . ($sinceStr ?? 'all');
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $params = [
            'state' => $state,
            'per_page' => 100,
            'sort' => 'created',
            'direction' => 'desc',
        ];

        if ($since !== null) {
            $params['since'] = $since->format(\DateTimeInterface::ATOM);
        }

        $data = $this->request('GET', "/repos/{$owner}/{$repo}/issues", $params);

        // Filter out pull requests from issues endpoint
        $issues = array_map(
            fn(array $item) => Issue::fromApiResponse($item),
            array_filter($data, fn(array $item) => !isset($item['pull_request']))
        );

        $this->cache->set($cacheKey, array_values($issues), self::CACHE_TTL_ISSUES);

        return array_values($issues);
    }

    public function getPullRequests(string $owner, string $repo, string $state = 'all'): array
    {
        $cacheKey = "github.{$owner}.{$repo}.pulls.{$state}";
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $params = [
            'state' => $state,
            'per_page' => 100,
            'sort' => 'created',
            'direction' => 'desc',
        ];

        $data = $this->request('GET', "/repos/{$owner}/{$repo}/pulls", $params);
        $pullRequests = array_map(
            fn(array $item) => PullRequest::fromApiResponse($item),
            $data
        );

        $this->cache->set($cacheKey, $pullRequests, self::CACHE_TTL_ISSUES);

        return $pullRequests;
    }

    public function getFirstCommitDate(string $owner, string $repo): ?\DateTimeInterface
    {
        $cacheKey = "github.{$owner}.{$repo}.first_commit";
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        // Get the repository first to check if it's empty
        $repository = $this->getRepository($owner, $repo);

        // Use the repository creation date as a fallback
        try {
            // Get the last page of commits (oldest first)
            $params = [
                'per_page' => 1,
                'sha' => $repository->defaultBranch,
            ];

            // First, get the commits to find total count via Link header
            $response = $this->requestWithResponse('GET', "/repos/{$owner}/{$repo}/commits", $params);
            $linkHeader = $response->getHeaderLine('Link');

            if (preg_match('/page=(\d+)>; rel="last"/', $linkHeader, $matches)) {
                $lastPage = (int) $matches[1];
                $params['page'] = $lastPage;
                $data = $this->request('GET', "/repos/{$owner}/{$repo}/commits", $params);

                if (!empty($data)) {
                    $commit = Commit::fromListResponse($data[count($data) - 1]);
                    $this->cache->set($cacheKey, $commit->authoredAt, self::CACHE_TTL_REPO);

                    return $commit->authoredAt;
                }
            }

            // Fallback to creation date
            $this->cache->set($cacheKey, $repository->createdAt, self::CACHE_TTL_REPO);

            return $repository->createdAt;
        } catch (\Exception) {
            return $repository->createdAt;
        }
    }

    public function getRepositoryContents(string $owner, string $repo, string $path = ''): array
    {
        $cacheKey = "github.{$owner}.{$repo}.contents." . md5($path);
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        try {
            $endpoint = "/repos/{$owner}/{$repo}/contents";
            if ($path !== '') {
                $endpoint .= '/' . ltrim($path, '/');
            }

            $data = $this->request('GET', $endpoint);

            // Single file returns object, directory returns array
            if (isset($data['type'])) {
                $contents = [FileContent::fromApiResponse($data)];
            } else {
                $contents = array_map(
                    fn(array $item) => FileContent::fromApiResponse($item),
                    $data
                );
            }

            $this->cache->set($cacheKey, $contents, self::CACHE_TTL_CONTENTS);

            return $contents;
        } catch (ApiException) {
            return [];
        }
    }

    public function getFileContent(string $owner, string $repo, string $path): ?FileContent
    {
        $contents = $this->getRepositoryContents($owner, $repo, $path);

        foreach ($contents as $content) {
            if (!$content->isDirectory() && $content->path === $path) {
                return $content;
            }
        }

        return null;
    }

    public function getRemainingRateLimit(): int
    {
        return $this->remainingRateLimit;
    }

    public function isAuthenticated(): bool
    {
        return $this->token !== null;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<mixed>
     */
    private function request(string $method, string $endpoint, array $params = []): array
    {
        $response = $this->requestWithResponse($method, $endpoint, $params);

        return json_decode($response->getBody()->getContents(), true) ?? [];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function requestWithResponse(string $method, string $endpoint, array $params = []): ResponseInterface
    {
        $options = [
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'CPTS/1.0',
            ],
        ];

        if ($this->token !== null) {
            $options['headers']['Authorization'] = 'Bearer ' . $this->token;
        }

        if (!empty($params)) {
            if ($method === 'GET') {
                $options['query'] = $params;
            } else {
                $options['json'] = $params;
            }
        }

        try {
            $response = $this->httpClient->request($method, self::BASE_URL . $endpoint, $options);
            $this->updateRateLimitFromResponse($response);

            return $response;
        } catch (RequestException $e) {
            $response = $e->getResponse();

            if ($response !== null) {
                $this->updateRateLimitFromResponse($response);
                $statusCode = $response->getStatusCode();

                if ($statusCode === 401) {
                    throw new AuthenticationException('Invalid GitHub token');
                }

                if ($statusCode === 403 && $this->remainingRateLimit === 0) {
                    $resetTime = (int) $response->getHeaderLine('X-RateLimit-Reset');
                    throw new RateLimitException(
                        0,
                        new \DateTimeImmutable("@{$resetTime}"),
                        'GitHub API rate limit exceeded'
                    );
                }

                if ($statusCode === 404) {
                    throw new ApiException("Resource not found: {$endpoint}");
                }
            }

            throw new ApiException($e->getMessage(), 0, $e);
        } catch (GuzzleException $e) {
            throw new NetworkException('Network error: ' . $e->getMessage(), 0, $e);
        }
    }

    private function updateRateLimitFromResponse(ResponseInterface $response): void
    {
        $remaining = $response->getHeaderLine('X-RateLimit-Remaining');
        if ($remaining !== '') {
            $this->remainingRateLimit = (int) $remaining;
        }
    }
}
