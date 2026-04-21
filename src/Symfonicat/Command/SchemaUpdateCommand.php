<?php

namespace Symfonicat\Command;

use Symfonicat\Service\ApplicationService;
use Symfonicat\Entity\Module;
use Symfonicat\Service\ModuleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'symfonicat:schema:update',
    description: 'Synchronize filesystem modules and applications with database rows.',
)]
final class SchemaUpdateCommand extends Command
{
    public function __construct(
        private readonly ApplicationService $applicationService,
        private readonly ModuleService $moduleService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $moduleResult = $this->moduleService->sync(function (Module $module, array $references) use ($input, $io): bool {
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

                return $this->confirmRequired(
                    $input,
                    $io,
                    sprintf('Delete those rows and remove module "%s"?', $module->getId()),
                    false,
                );
            });

            $applicationResult = $this->applicationService->sync(function (array $applicationIds) use ($input, $io): bool {
                $io->section('Missing applications');
                $io->listing(array_map(
                    static fn (string $applicationId): string => sprintf('%s from assets/application/%s', $applicationId, $applicationId),
                    $applicationIds,
                ));

                return $this->confirmRequired($input, $io, 'Create these application rows?', false);
            });
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ($moduleResult['created'] !== []) {
            $io->section('Created modules');
            $io->listing(array_map(
                static fn (array $module): string => sprintf('%s (%s)', $module['id'], $module['name']),
                $moduleResult['created'],
            ));
        }

        if ($moduleResult['updated'] !== []) {
            $io->section('Updated modules');
            $io->listing(array_map(
                static fn (array $module): string => sprintf('%s: "%s" -> "%s"', $module['id'], $module['from'], $module['to']),
                $moduleResult['updated'],
            ));
        }

        if ($moduleResult['deleted'] !== []) {
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
                $moduleResult['deleted'],
            ));
        }

        if ($applicationResult['created'] !== []) {
            $io->section('Created applications');
            $io->listing(array_map(
                static fn (array $application): string => $application['id'],
                $applicationResult['created'],
            ));
        }

        if (
            $moduleResult['created'] === []
            && $moduleResult['updated'] === []
            && $moduleResult['deleted'] === []
            && $applicationResult['created'] === []
        ) {
            $io->success('Module and application rows already match filesystem assets.');

            return Command::SUCCESS;
        }

        $io->success('Module and application rows synchronized from filesystem assets.');

        return Command::SUCCESS;
    }

    private function confirmRequired(InputInterface $input, SymfonyStyle $io, string $question, bool $default): bool
    {
        if (!$input->isInteractive() || !$this->hasInteractiveTerminal()) {
            throw new \RuntimeException(sprintf(
                'Confirmation is required for "%s", but stdin is not interactive. Run this command with an attached terminal; with Docker use: docker exec -it php bin/console symfonicat:schema:update',
                $question,
            ));
        }

        return $io->confirm($question, $default);
    }

    private function hasInteractiveTerminal(): bool
    {
        return defined('STDIN') && function_exists('stream_isatty') && stream_isatty(STDIN);
    }
}
