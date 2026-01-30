<?php

declare(strict_types=1);

namespace Cpts\Command;

use Composer\Command\BaseCommand;
use Cpts\Cache\FilesystemCache;
use Cpts\Config\ComposerConfig;
use Cpts\Api\GitHub\GitHubClient;
use Cpts\Api\Packagist\PackagistClient;
use Cpts\Metric\MetricRegistry;
use Cpts\Package\PackageResolver;
use Cpts\Score\ScoreCalculator;
use Cpts\Score\TrustBonus;
use GuzzleHttp\Client;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ScoreCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('cpts:score')
            ->setDescription('Get CPTS score for a specific package')
            ->addArgument('package', InputArgument::REQUIRED, 'Package name (vendor/package)')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format (detailed, json, minimal)', 'detailed')
            ->addOption('no-cache', null, InputOption::VALUE_NONE, 'Bypass cache');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $packageName = $input->getArgument('package');
        $io = $this->getIO();
        $format = $input->getOption('format');

        $composer = $this->requireComposer();
        $config = new ComposerConfig($composer);

        if ($config->isDisabled()) {
            $io->writeError('<comment>CPTS is disabled via CPTS_DISABLE environment variable</comment>');

            return 0;
        }

        if ($format !== 'minimal') {
            $io->write(sprintf('Calculating CPTS score for <info>%s</info>...', $packageName));
            $io->write('');
        }

        try {
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

            // Calculate score
            $packageInfo = $resolver->resolve($packageName);
            $result = $calculator->calculate($packageInfo);

            // Output based on format
            if ($format === 'json') {
                $io->write(json_encode($result->toArray(), JSON_PRETTY_PRINT));

                return 0;
            }

            if ($format === 'minimal') {
                $io->write(sprintf('%.1f', $result->getScore()));

                return 0;
            }

            // Detailed format
            $this->displayDetailed($result, $io, $config);

            return $result->getScore() >= $config->getMinCpts() ? 0 : 1;
        } catch (\Exception $e) {
            $io->writeError(sprintf('<error>Error: %s</error>', $e->getMessage()));

            return 1;
        }
    }

    private function displayDetailed(\Cpts\Score\ScoreResult $result, \Composer\IO\IOInterface $io, ComposerConfig $config): void
    {
        $scoreColor = match (true) {
            $result->getScore() >= 80 => 'green',
            $result->getScore() >= 60 => 'yellow',
            $result->getScore() >= 40 => 'cyan',
            default => 'red',
        };

        $io->write(sprintf('<info>Package:</info>     %s', $result->getPackage()));
        $io->write(sprintf('<info>CPTS Score:</info>  <fg=%s>%.1f</> / 100 (Grade: %s)', $scoreColor, $result->getScore(), $result->getGrade()));
        $io->write(sprintf('<info>Trust Bonus:</info> %+.2f (raw: %+.2f)', $result->getTrustBonus(), $result->getRawTrustBonus()));
        $io->write(sprintf('<info>Min CPTS:</info>    %d', $config->getMinCpts()));
        $io->write(sprintf(
            '<info>Status:</info>      %s',
            $result->getScore() >= $config->getMinCpts()
                ? '<fg=green>PASS</>'
                : '<fg=red>FAIL</>'
        ));
        $io->write('');

        $io->write('<info>Metric Breakdown:</info>');
        $io->write('');

        foreach ($result->getMetricResults() as $name => $metric) {
            if ($metric->failed) {
                $io->write(sprintf('  %-20s <fg=yellow>FAILED</> (%s)', $name, $metric->error));
                continue;
            }

            $normalized = $metric->normalizedScore;
            $color = match (true) {
                $normalized >= 0.8 => 'green',
                $normalized >= 0.5 => 'yellow',
                default => 'red',
            };

            $io->write(sprintf(
                '  %-20s <fg=%s>%.2f</> (weight: %.1f, contribution: %.2f)',
                $name,
                $color,
                $normalized,
                $metric->weight,
                $metric->getWeightedScore()
            ));
        }

        $io->write('');
        $io->write(sprintf('<info>Calculated:</info>  %s', $result->calculatedAt->format('Y-m-d H:i:s')));
    }
}
