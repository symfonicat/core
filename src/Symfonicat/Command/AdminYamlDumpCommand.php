<?php

namespace Symfonicat\Command;

use Symfonicat\Service\AdminYaml;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'symfonicat:dump',
    description: 'Dump every symfonicat_* table into config/packages/symfonicat.yaml.',
)]
final class AdminYamlDumpCommand extends Command
{
    public function __construct(
        private readonly AdminYaml $adminYaml,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $counts = $this->adminYaml->dump();
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Dumped %d rows from %d Symfonicat tables to config/packages/symfonicat.yaml.',
            array_sum($counts),
            count($counts),
        ));

        return Command::SUCCESS;
    }
}
