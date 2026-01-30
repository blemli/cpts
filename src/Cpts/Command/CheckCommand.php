<?php

declare(strict_types=1);

namespace Cpts\Command;

use Composer\Command\BaseCommand;
use Cpts\Api\Exception\RateLimitException;
use Cpts\Cache\FilesystemCache;
use Cpts\Config\ComposerConfig;
use Cpts\Api\GitHub\GitHubClient;
use Cpts\Api\Packagist\PackagistClient;
use Cpts\Metric\MetricRegistry;
use Cpts\Package\PackageResolver;
use Cpts\Package\TrustedPackageMatcher;
use Cpts\Score\ScoreCalculator;
use Cpts\Score\TrustBonus;
use GuzzleHttp\Client;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CheckCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('cpts:check')
            ->setDescription('Check CPTS scores for all installed dependencies')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format (table, json)', 'table')
            ->addOption('fail-under', null, InputOption::VALUE_REQUIRED, 'Fail if any package scores below this threshold')
            ->addOption('no-cache', null, InputOption::VALUE_NONE, 'Bypass cache and fetch fresh data')
            ->addOption('dev', null, InputOption::VALUE_NONE, 'Include dev dependencies')
            ->addOption('only', null, InputOption::VALUE_REQUIRED, 'Only check specific package');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $composer = $this->requireComposer();
        $io = $this->getIO();

        $config = new ComposerConfig($composer);

        if ($config->isDisabled()) {
            $io->write('<comment>CPTS is disabled via CPTS_DISABLE environment variable</comment>');

            return 0;
        }

        $io->write('<info>CPTS Check</info> - Analyzing dependencies...');
        $io->write('');

        // Build dependencies
        $cache = $input->getOption('no-cache')
            ? new \Cpts\Cache\NullCache()
            : new FilesystemCache($config->getCacheDir());

        $httpClient = new Client(['timeout' => 30]);
        $gitHub = new GitHubClient($httpClient, $cache, $config->getGitHubToken());
        $packagist = new PackagistClient($httpClient, $cache);
        $resolver = new PackageResolver($gitHub, $packagist);

        $metricRegistry = new MetricRegistry($config);
        $trustBonus = new TrustBonus();
        $calculator = new ScoreCalculator($metricRegistry, $trustBonus);
        $matcher = new TrustedPackageMatcher($config->getTrustedPackages());

        // Get packages from lock file
        $packages = $this->getPackages($composer, $input->getOption('dev'));

        // Warn if unauthenticated with many packages
        if (!$gitHub->isAuthenticated() && count($packages) > 30) {
            $io->write('<warning>Warning: No GITHUB_TOKEN set. Rate limit is 60 requests/hour.</warning>');
            $io->write('<comment>Set GITHUB_TOKEN in .env for higher limits (5000/hour).</comment>');
            $io->write('');
        }
        $threshold = $input->getOption('fail-under') ?? $config->getMinCpts();
        $onlyPackage = $input->getOption('only');

        $results = [];
        $failCount = 0;
        $passCount = 0;
        $trustCount = 0;
        $errorCount = 0;
        $rateLimitHit = false;

        foreach ($packages as $packageData) {
            $packageName = $packageData['name'];

            // Filter if --only specified
            if ($onlyPackage !== null && $packageName !== $onlyPackage) {
                continue;
            }

            // Skip trusted packages
            if ($matcher->matches($packageName)) {
                $results[] = [
                    'package' => $packageName,
                    'score' => null,
                    'status' => 'TRUSTED',
                    'grade' => '-',
                ];
                $trustCount++;
                continue;
            }

            try {
                $io->write("  Checking <info>{$packageName}</info>...", false);
                $packageInfo = $resolver->resolve($packageName);
                $scoreResult = $calculator->calculate($packageInfo);

                $status = $scoreResult->getScore() >= $threshold ? 'PASS' : 'FAIL';

                $results[] = [
                    'package' => $packageName,
                    'score' => $scoreResult->getScore(),
                    'status' => $status,
                    'grade' => $scoreResult->getGrade(),
                    'result' => $scoreResult,
                ];

                if ($status === 'PASS') {
                    $passCount++;
                    $io->overwrite("  <info>{$packageName}</info>: <fg=green>{$scoreResult->getScore()}</> ({$scoreResult->getGrade()})");
                } else {
                    $failCount++;
                    $io->overwrite("  <info>{$packageName}</info>: <fg=red>{$scoreResult->getScore()}</> ({$scoreResult->getGrade()}) <error>FAIL</error>");
                }
            } catch (RateLimitException $e) {
                $rateLimitHit = true;
                $results[] = [
                    'package' => $packageName,
                    'score' => null,
                    'status' => 'RATE_LIMITED',
                    'grade' => '-',
                    'error' => $e->getMessage(),
                ];
                $errorCount++;
                $io->overwrite("  <info>{$packageName}</info>: <fg=yellow>RATE LIMITED</>");
            } catch (\Exception $e) {
                $results[] = [
                    'package' => $packageName,
                    'score' => null,
                    'status' => 'ERROR',
                    'grade' => '-',
                    'error' => $e->getMessage(),
                ];
                $errorCount++;
                $io->overwrite("  <info>{$packageName}</info>: <fg=yellow>ERROR</> - " . $e->getMessage());
            }
        }

        $io->write('');
        $io->write('---');
        $io->write(sprintf(
            'Checked %d packages: <fg=green>%d passed</>, <fg=red>%d failed</>, <fg=cyan>%d trusted</>, <fg=yellow>%d errors</>',
            count($results),
            $passCount,
            $failCount,
            $trustCount,
            $errorCount
        ));

        // Show rate limit status
        $remaining = $gitHub->getRemainingRateLimit();
        if ($rateLimitHit || $remaining < 10) {
            $io->write('');
            $io->write('<error>GitHub API rate limit exhausted!</error>');
            $io->write('<comment>Scores may be inaccurate. Set GITHUB_TOKEN in .env for 5000 requests/hour.</comment>');
        } elseif (!$gitHub->isAuthenticated()) {
            $io->write(sprintf('<comment>GitHub API: %d requests remaining (set GITHUB_TOKEN for higher limits)</comment>', $remaining));
        }

        if ($input->getOption('format') === 'json') {
            $io->write('');
            $io->write(json_encode([
                'packages' => array_map(fn($r) => [
                    'name' => $r['package'],
                    'score' => $r['score'],
                    'grade' => $r['grade'],
                    'status' => $r['status'],
                ], $results),
                'summary' => [
                    'total' => count($results),
                    'passed' => $passCount,
                    'failed' => $failCount,
                    'trusted' => $trustCount,
                    'errors' => $errorCount,
                ],
            ], JSON_PRETTY_PRINT));
        }

        // Only fail if --fail-under was explicitly set
        $failUnderExplicit = $input->getOption('fail-under') !== null;

        return ($failUnderExplicit && $failCount > 0) ? 1 : 0;
    }

    /**
     * @return array<int, array{name: string}>
     */
    private function getPackages(\Composer\Composer $composer, bool $includeDev): array
    {
        $locker = $composer->getLocker();

        if (!$locker->isLocked()) {
            return [];
        }

        $lockData = $locker->getLockData();
        $packages = $lockData['packages'] ?? [];

        if ($includeDev) {
            $packages = array_merge($packages, $lockData['packages-dev'] ?? []);
        }

        return $packages;
    }
}
