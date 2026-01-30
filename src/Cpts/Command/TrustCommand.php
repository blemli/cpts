<?php

declare(strict_types=1);

namespace Cpts\Command;

use Composer\Command\BaseCommand;
use Composer\Factory;
use Composer\Json\JsonFile;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TrustCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('cpts:trust')
            ->setDescription('Add package(s) to trusted_packages list')
            ->addArgument('packages', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'Package names or vendor/* patterns')
            ->addOption('remove', 'r', InputOption::VALUE_NONE, 'Remove from trusted list instead');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getIO();
        $packages = $input->getArgument('packages');
        $remove = $input->getOption('remove');

        $composerFile = Factory::getComposerFile();
        $json = new JsonFile($composerFile);

        if (!$json->exists()) {
            $io->writeError('<error>composer.json not found</error>');
            return 1;
        }

        $config = $json->read();
        $trusted = $config['extra']['cpts']['trusted_packages'] ?? [];

        foreach ($packages as $package) {
            if ($remove) {
                $key = array_search($package, $trusted, true);
                if ($key !== false) {
                    unset($trusted[$key]);
                    $io->write("<info>Removed:</info> {$package}");
                } else {
                    $io->write("<comment>Not found:</comment> {$package}");
                }
            } else {
                if (!in_array($package, $trusted, true)) {
                    $trusted[] = $package;
                    $io->write("<info>Added:</info> {$package}");
                } else {
                    $io->write("<comment>Already trusted:</comment> {$package}");
                }
            }
        }

        // Re-index array
        $trusted = array_values($trusted);

        // Ensure extra.cpts structure exists
        if (!isset($config['extra'])) {
            $config['extra'] = [];
        }
        if (!isset($config['extra']['cpts'])) {
            $config['extra']['cpts'] = [];
        }

        $config['extra']['cpts']['trusted_packages'] = $trusted;

        $json->write($config);

        $io->write('');
        $io->write('<info>Updated composer.json</info>');

        return 0;
    }
}
