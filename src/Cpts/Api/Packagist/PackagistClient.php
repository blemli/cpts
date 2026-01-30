<?php

declare(strict_types=1);

namespace Cpts\Api\Packagist;

use Cpts\Api\Exception\ApiException;
use Cpts\Api\Exception\NetworkException;
use Cpts\Api\Packagist\Dto\Package;
use Cpts\Api\Packagist\Dto\Stats;
use Cpts\Cache\CacheInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

class PackagistClient implements PackagistClientInterface
{
    private const BASE_URL = 'https://packagist.org';
    private const CACHE_TTL_PACKAGE = 43200;   // 12 hours
    private const CACHE_TTL_STATS = 3600;      // 1 hour

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly CacheInterface $cache,
    ) {
    }

    public function getPackage(string $vendor, string $package): Package
    {
        $cacheKey = "packagist.{$vendor}.{$package}.meta";
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $data = $this->request("/packages/{$vendor}/{$package}.json");
        $pkg = Package::fromApiResponse($data);

        $this->cache->set($cacheKey, $pkg, self::CACHE_TTL_PACKAGE);

        return $pkg;
    }

    public function getStats(string $vendor, string $package): Stats
    {
        $cacheKey = "packagist.{$vendor}.{$package}.stats";
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $data = $this->request("/packages/{$vendor}/{$package}.json");
        $stats = Stats::fromApiResponse($data);

        $this->cache->set($cacheKey, $stats, self::CACHE_TTL_STATS);

        return $stats;
    }

    public function getDependentsCount(string $vendor, string $package): int
    {
        return $this->getStats($vendor, $package)->dependents;
    }

    public function packageExists(string $vendor, string $package): bool
    {
        try {
            $this->getPackage($vendor, $package);

            return true;
        } catch (ApiException) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $endpoint): array
    {
        $options = [
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'CPTS/1.0',
            ],
        ];

        try {
            $response = $this->httpClient->request('GET', self::BASE_URL . $endpoint, $options);
            $body = $response->getBody()->getContents();

            return json_decode($body, true) ?? [];
        } catch (RequestException $e) {
            $response = $e->getResponse();

            if ($response !== null && $response->getStatusCode() === 404) {
                throw new ApiException("Package not found: {$endpoint}");
            }

            throw new ApiException($e->getMessage(), 0, $e);
        } catch (GuzzleException $e) {
            throw new NetworkException('Network error: ' . $e->getMessage(), 0, $e);
        }
    }
}
