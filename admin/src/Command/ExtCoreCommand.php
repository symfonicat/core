<?php

namespace Symfonicat\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'symfonicat:ext:core',
    description: 'Output the native ext directory names from native/ext.',
)]
final class ExtCoreCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln($this->coreManifest());

        return Command::SUCCESS;
    }

    private function coreManifest(): string
    {
        $extDirectory = rtrim($this->projectDir, '/').'/native/ext';
        if (!is_dir($extDirectory)) {
            return '';
        }

        $directories = glob($extDirectory.'/*', GLOB_ONLYDIR) ?: [];
        sort($directories, SORT_STRING);

        $names = [];
        foreach ($directories as $directory) {
            $name = basename($directory);
            if ($name === '') {
                continue;
            }

            $names[] = $name;
        }

        return implode(' ', $names);
    }
}
