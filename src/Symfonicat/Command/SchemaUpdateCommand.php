<?php

namespace Symfonicat\Command;

use Symfonicat\Entity\Module;
use Symfonicat\Service\ModuleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'symfonicat:schema:update',
    description: 'Synchronize filesystem modules with the module rows stored in the database.',
)]
final class SchemaUpdateCommand extends Command
{
    public function __construct(
        private readonly ModuleService $moduleService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $result = $this->moduleService->sync(function (Module $module, array $references) use ($io): bool {
                $io->warning(sprintf(
                    'Module "%s" no longer exists under assets/modules and still has referencing entity rows.',
                    $module->getId(),
                ));

                $io->table(
                    ['Table', 'Entity association', 'Rows', 'Delete action'],
                    array_map(
                        static fn (array $reference): array => [
                            $reference['table'],
                            sprintf('%s::%s', $reference['entity'], $reference['association']),
                            (string) $reference['count'],
                            $reference['delete_action'],
                        ],
                        $references,
                    ),
                );

                return $io->confirm(
                    sprintf('Delete those rows and remove module "%s"?', $module->getId()),
                    false,
                );
            });
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ($result['created'] !== []) {
            $io->section('Created modules');
            $io->listing(array_map(
                static fn (array $module): string => sprintf('%s (%s)', $module['id'], $module['name']),
                $result['created'],
            ));
        }

        if ($result['updated'] !== []) {
            $io->section('Updated modules');
            $io->listing(array_map(
                static fn (array $module): string => sprintf('%s: "%s" -> "%s"', $module['id'], $module['from'], $module['to']),
                $result['updated'],
            ));
        }

        if ($result['deleted'] !== []) {
            $io->section('Deleted modules');
            $io->listing(array_map(
                static function (array $module): string {
                    $details = sprintf('%s (%s)', $module['id'], $module['name']);
                    if ($module['references'] === []) {
                        return $details;
                    }

                    $referenceSummary = implode(', ', array_map(
                        static fn (array $reference): string => sprintf('%s (%d)', $reference['table'], $reference['count']),
                        $module['references'],
                    ));

                    return sprintf('%s after removing references from %s', $details, $referenceSummary);
                },
                $result['deleted'],
            ));
        }

        if (
            $result['created'] === []
            && $result['updated'] === []
            && $result['deleted'] === []
        ) {
            $io->success('Module rows already match assets/modules.');

            return Command::SUCCESS;
        }

        $io->success('Module rows synchronized from assets/modules.');

        return Command::SUCCESS;
    }
}
