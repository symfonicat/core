<?php

namespace Symfonicat\Command;

use Symfonicat\Service\AdminYaml;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'symfonicat:load',
    description: 'Load config/packages/symfonicat.yaml symfonicat.admin rows into the database.',
)]
final class AdminYamlLoadCommand extends Command
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
            $counts = $this->adminYaml->load();
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ($counts === []) {
            $io->success('No symfonicat.admin YAML found in config/packages/symfonicat.yaml.');

            return Command::SUCCESS;
        }

        $io->success(sprintf(
            'Loaded %d rows into %d Symfonicat tables from config/packages/symfonicat.yaml.',
            array_sum($counts),
            count($counts),
        ));

        return Command::SUCCESS;
    }
}
