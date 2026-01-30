<?php

declare(strict_types=1);

namespace Cpts;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
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
