<?php

declare(strict_types=1);

namespace Cpts\Event;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Script\Event;
use Cpts\Api\GitHub\GitHubClient;
use Cpts\Api\Packagist\PackagistClient;
use Cpts\Cache\FilesystemCache;
use Cpts\Config\ComposerConfig;
use Cpts\Metric\MetricRegistry;
use Cpts\Package\PackageResolver;
use Cpts\Package\TrustedPackageMatcher;
use Cpts\Score\ScoreCalculator;
use Cpts\Score\TrustBonus;
use GuzzleHttp\Client;

class PackageEventSubscriber
{
    private ComposerConfig $config;
    private ?TrustedPackageMatcher $matcher = null;
    private ?PackageResolver $resolver = null;
    private ?ScoreCalculator $calculator = null;

    /** @var ValidationResult[] */
    private array $validationResults = [];

    public function __construct(
        private readonly Composer $composer,
        private readonly IOInterface $io,
    ) {
        $this->config = new ComposerConfig($composer);
    }

    public function handlePrePackageInstall(PackageEvent $event): void
    {
        $operation = $event->getOperation();

        if (!$operation instanceof InstallOperation) {
            return;
        }

        $package = $operation->getPackage();
        $this->validatePackage($package->getName());
    }

    public function handlePrePackageUpdate(PackageEvent $event): void
    {
        $operation = $event->getOperation();

        if (!$operation instanceof UpdateOperation) {
            return;
        }

        $package = $operation->getTargetPackage();
        $this->validatePackage($package->getName());
    }

    public function handlePostInstall(Event $event): void
    {
        $this->displaySummary();
    }

    public function handlePostUpdate(Event $event): void
    {
        $this->displaySummary();
    }

    private function validatePackage(string $packageName): void
    {
        // Check if trusted
        if ($this->getMatcher()->matches($packageName)) {
            $this->validationResults[] = new ValidationResult(
                $packageName,
                ValidationResult::STATUS_TRUSTED
            );

            return;
        }

        try {
            $packageInfo = $this->getResolver()->resolve($packageName);
            $scoreResult = $this->getCalculator()->calculate($packageInfo);

            $status = $scoreResult->getScore() >= $this->config->getMinCpts()
                ? ValidationResult::STATUS_PASS
                : ValidationResult::STATUS_FAIL;

            $result = new ValidationResult($packageName, $status, $scoreResult);
            $this->validationResults[] = $result;

            // Display inline result
            $metricsStr = $scoreResult->getMetricEmojis();
            if ($status === ValidationResult::STATUS_PASS) {
                $this->io->write(sprintf(
                    '  <info>%s</info>: %.1f (%s) %s',
                    $packageName,
                    $scoreResult->getScore(),
                    $scoreResult->getGrade(),
                    $metricsStr
                ));
            } else {
                $this->io->write(sprintf(
                    '  <info>%s</info>: %.1f (%s) %s <comment>below %d</comment>',
                    $packageName,
                    $scoreResult->getScore(),
                    $scoreResult->getGrade(),
                    $metricsStr,
                    $this->config->getMinCpts()
                ));
            }
        } catch (\Exception $e) {
            $this->validationResults[] = new ValidationResult(
                $packageName,
                ValidationResult::STATUS_ERROR,
                null,
                $e->getMessage()
            );

            $this->io->write(sprintf(
                '  <info>CPTS</info> %s: <fg=yellow>ERROR</> %s',
                $packageName,
                $e->getMessage()
            ));
        }
    }

    private function displaySummary(): void
    {
        if (empty($this->validationResults)) {
            return;
        }

        $pass = 0;
        $fail = 0;
        $trusted = 0;
        $error = 0;

        foreach ($this->validationResults as $result) {
            match ($result->status) {
                ValidationResult::STATUS_PASS => $pass++,
                ValidationResult::STATUS_FAIL => $fail++,
                ValidationResult::STATUS_TRUSTED => $trusted++,
                ValidationResult::STATUS_ERROR => $error++,
                default => null,
            };
        }

        $this->io->write('');
        $this->io->write(sprintf(
            '<info>CPTS Summary:</info> %d passed, %d failed, %d trusted, %d errors',
            $pass,
            $fail,
            $trusted,
            $error
        ));

        // List failed packages
        if ($fail > 0) {
            $this->io->write('');
            $this->io->write('<comment>Packages below minimum CPTS:</comment>');

            foreach ($this->validationResults as $result) {
                if ($result->status === ValidationResult::STATUS_FAIL) {
                    $this->io->write(sprintf(
                        '  - %s: %.1f',
                        $result->packageName,
                        $result->getScore()
                    ));
                }
            }

            $this->io->write('');
            $this->io->write('Run <info>composer cpts:score vendor/package</info> for details.');
        }

        // Clear results for next run
        $this->validationResults = [];
    }

    private function getMatcher(): TrustedPackageMatcher
    {
        if ($this->matcher === null) {
            $this->matcher = new TrustedPackageMatcher($this->config->getTrustedPackages());
        }

        return $this->matcher;
    }

    private function getResolver(): PackageResolver
    {
        if ($this->resolver === null) {
            $cache = new FilesystemCache($this->config->getCacheDir());
            $httpClient = new Client(['timeout' => 30]);
            $gitHub = new GitHubClient($httpClient, $cache, $this->config->getGitHubToken());
            $packagist = new PackagistClient($httpClient, $cache);
            $this->resolver = new PackageResolver($gitHub, $packagist);
        }

        return $this->resolver;
    }

    private function getCalculator(): ScoreCalculator
    {
        if ($this->calculator === null) {
            $metricRegistry = new MetricRegistry($this->config);
            $trustBonus = new TrustBonus();
            $this->calculator = new ScoreCalculator($metricRegistry, $trustBonus);
        }

        return $this->calculator;
    }
}
