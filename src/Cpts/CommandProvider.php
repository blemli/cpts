<?php

declare(strict_types=1);

namespace Cpts;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Cpts\Command\CheckCommand;
use Cpts\Command\ScoreCommand;
use Cpts\Command\TrustCommand;

class CommandProvider implements CommandProviderCapability
{
    /**
     * @return \Composer\Command\BaseCommand[]
     */
    public function getCommands(): array
    {
        return [
            new CheckCommand(),
            new ScoreCommand(),
            new TrustCommand(),
        ];
    }
}
