<?php

namespace Symfonicat\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;

#[AsCommand(
    name: 'symfonicat:electron:package',
    description: 'Package Electron project bundles for the current platform.',
    aliases: ['electron:package'],
)]
final class ElectronPackageCommand extends AbstractElectronCommand
{
    protected function electronArguments(InputInterface $input): array
    {
        return [];
    }
}
