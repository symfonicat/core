<?php

namespace Symfonicat\Command;

use Symfonicat\Service\NativeDiscoveryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'symfonicat:discover:ext:names',
    description: 'Output ext subdirectory names for matching package roots.',
)]
final class DiscoverExtNamesCommand extends Command
{
    public function __construct(
        private readonly NativeDiscoveryService $nativeDiscoveryService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('pattern', InputArgument::REQUIRED, 'Root path pattern.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->nativeDiscoveryService->discoverNames('ext', (string) $input->getArgument('pattern')) as $name) {
            $output->writeln($name);
        }

        return Command::SUCCESS;
    }
}
