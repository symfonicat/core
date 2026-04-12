<?php

namespace Symfonicat\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;

#[AsCommand(
    name: 'symfonicat:electron:build',
    description: 'Build Electron project packages for the current platform.',
    aliases: ['electron:build'],
)]
final class ElectronBuildCommand extends AbstractElectronCommand
{
    protected function electronArguments(InputInterface $input): array
    {
        return [];
    }
}
