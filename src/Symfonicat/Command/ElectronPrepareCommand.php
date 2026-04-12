<?php

namespace Symfonicat\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;

#[AsCommand(
    name: 'symfonicat:electron:prepare',
    description: 'Prepare Electron project directories without packaging them.',
    aliases: ['electron:prepare'],
)]
final class ElectronPrepareCommand extends AbstractElectronCommand
{
    protected function electronArguments(InputInterface $input): array
    {
        return ['--prepare-only'];
    }
}
