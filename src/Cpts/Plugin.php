<?php

declare(strict_types=1);

namespace Cpts;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Cpts\Config\ComposerConfig;
use Cpts\Event\PackageEventSubscriber;

class Plugin implements PluginInterface, Capable, EventSubscriberInterface
{
    public const VERSION = '0.1.0';

    private ?Composer $composer = null;
    private ?IOInterface $io = null;
    private ?PackageEventSubscriber $eventSubscriber = null;
    private ?ComposerConfig $config = null;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;

        $this->ensureConfigExists($io);
    }

    private function ensureConfigExists(IOInterface $io): void
    {
        try {
            $composerFile = Factory::getComposerFile();
            $json = new JsonFile($composerFile);

            if (!$json->exists()) {
                return;
            }

            $config = $json->read();

            // Check if cpts config already exists
            if (isset($config['extra']['cpts'])) {
                return;
            }

            // Add default config
            if (!isset($config['extra'])) {
                $config['extra'] = [];
            }

            $config['extra']['cpts'] = [
                'min_cpts' => 20,
                'trusted_packages' => [],
            ];

            $json->write($config);

            $io->write('<info>CPTS:</info> Added default config to composer.json');

            // Offer to add cache dir to .gitignore
            $this->offerGitignore($io);
        } catch (\Exception) {
            // Silently fail - config is optional
        }
    }

    private function offerGitignore(IOInterface $io): void
    {
        $gitignorePath = dirname(Factory::getComposerFile()) . '/.gitignore';
        $cacheEntry = '.cpts-cache/';

        // Check if .gitignore exists and already has the entry
        if (file_exists($gitignorePath)) {
            $content = file_get_contents($gitignorePath);
            if ($content !== false && str_contains($content, $cacheEntry)) {
                return; // Already ignored
            }
        }

        // Ask user
        if (!$io->askConfirmation('<info>CPTS:</info> Add .cpts-cache/ to .gitignore? [Y/n] ', true)) {
            return;
        }

        // Add to .gitignore
        $newLine = file_exists($gitignorePath) && !str_ends_with((string) file_get_contents($gitignorePath), "\n") ? "\n" : '';
        file_put_contents($gitignorePath, $newLine . $cacheEntry . "\n", FILE_APPEND);

        $io->write('<info>CPTS:</info> Added .cpts-cache/ to .gitignore');
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // Cleanup if needed
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // Optionally cleanup cache directory
    }

    /**
     * @return array<string, string>
     */
    public function getCapabilities(): array
    {
        return [
            'Composer\Plugin\Capability\CommandProvider' => CommandProvider::class,
        ];
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'pre-package-install' => ['onPrePackageInstall', 0],
            'pre-package-update' => ['onPrePackageUpdate', 0],
            'post-install-cmd' => ['onPostInstall', 0],
            'post-update-cmd' => ['onPostUpdate', 0],
        ];
    }

    public function onPrePackageInstall(mixed $event): void
    {
        if ($this->isDisabled()) {
            return;
        }
        $this->getEventSubscriber()->handlePrePackageInstall($event);
    }

    public function onPrePackageUpdate(mixed $event): void
    {
        if ($this->isDisabled()) {
            return;
        }
        $this->getEventSubscriber()->handlePrePackageUpdate($event);
    }

    public function onPostInstall(mixed $event): void
    {
        if ($this->isDisabled()) {
            return;
        }
        $this->getEventSubscriber()->handlePostInstall($event);
    }

    public function onPostUpdate(mixed $event): void
    {
        if ($this->isDisabled()) {
            return;
        }
        $this->getEventSubscriber()->handlePostUpdate($event);
    }

    private function getEventSubscriber(): PackageEventSubscriber
    {
        if ($this->eventSubscriber === null) {
            if ($this->composer === null || $this->io === null) {
                throw new \RuntimeException('Plugin not activated');
            }
            $this->eventSubscriber = new PackageEventSubscriber(
                $this->composer,
                $this->io
            );
        }

        return $this->eventSubscriber;
    }

    private function getConfig(): ComposerConfig
    {
        if ($this->config === null) {
            if ($this->composer === null) {
                throw new \RuntimeException('Plugin not activated');
            }
            $this->config = new ComposerConfig($this->composer);
        }

        return $this->config;
    }

    private function isDisabled(): bool
    {
        return $this->getConfig()->isDisabled();
    }
}
