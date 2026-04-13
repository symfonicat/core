<?php

namespace Symfonicat\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;

#[AsCommand(
    name: 'symfonicat:electron:dev',
    description: 'Prepare Electron project directories for interactive local development.',
    aliases: ['electron:dev'],
)]
final class ElectronDevCommand extends AbstractElectronCommand
{
    protected function electronArguments(InputInterface $input): array
    {
        return ['--prepare-only'];
    }
}
