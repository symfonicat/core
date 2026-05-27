<?php

namespace Symfonicat\Command;

use Symfonicat\Service\ScriptlingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'symfonicat:scriptling:bash',
    description: 'Emit xcaddy build flags for all discovered Symfonicat extensions.',
)]
final class ScriptlingBashCommand extends Command
{
    public function __construct(
        private readonly ScriptlingService $scriptlingService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->scriptlingService->xcaddyFlags() as $flag) {
            $output->writeln($flag);
        }

        return Command::SUCCESS;
    }
}
