<?php

namespace Symfonicat\Command;

use Symfonicat\Service\ScriptlingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'symfonicat:scriptling:copy',
    description: 'Emit Bash commands that copy discovered Symfonicat extensions into /symfonicat/extensions.',
)]
final class ScriptlingCopyCommand extends Command
{
    public function __construct(
        private readonly ScriptlingService $scriptlingService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->scriptlingService->copyScriptLines() as $line) {
            $output->writeln($line);
        }

        return Command::SUCCESS;
    }
}
