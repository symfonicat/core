<?php

namespace Symfonicat\Command;

use Symfonicat\Entity\Module;
use Symfonicat\Service\ParcelService;
use Symfonicat\Service\DomainService;
use Symfonicat\Service\ModuleService;
use Symfonicat\Service\SubdomainService;
use Symfonicat\Service\SchemaSynchronizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'symfonicat:schema:update',
    description: 'Synchronize the Doctrine schema and installed configured-vendor package rows.',
)]
final class SchemaUpdateCommand extends Command
{
    public function __construct(
        private readonly ParcelService $parcelService,
        private readonly DomainService $domainService,
        private readonly ModuleService $moduleService,
        private readonly SubdomainService $subdomainService,
        private readonly SchemaSynchronizer $schemaSynchronizer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $this->schemaSynchronizer->synchronize();
            $shouldAskForConfirmation = $this->shouldAskForConfirmation($input);

            $parcelResult = $this->parcelService->sync();

            $moduleResult = $this->moduleService->sync($shouldAskForConfirmation ? function (Module $module, array $references) use ($input, $io): bool {
                $io->warning(sprintf(
                    'Module "%s" is no longer provided by an installed configured-vendor package and still has referencing entity rows.',
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
            } : null);

            $domainResult = $this->domainService->sync($shouldAskForConfirmation ? function (array $domainIds) use ($input, $io): bool {
                $io->section('Missing domains');
                $io->listing(array_map(
                    static fn (string $domainId): string => sprintf('%s from installed package assets', $domainId),
                    $domainIds,
                ));

                return $this->confirmRequired($input, $io, 'Create these domain rows?', false);
            } : null);

            $subdomainResult = $this->subdomainService->sync($shouldAskForConfirmation ? function (array $subdomainIds) use ($input, $io): bool {
                $io->section('Missing subdomains');
                $io->listing(array_map(
                    static fn (string $subdomainId): string => sprintf('%s from installed package assets', $subdomainId),
                    $subdomainIds,
                ));

                return $this->confirmRequired($input, $io, 'Create these subdomain rows?', false);
            } : null);
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ($parcelResult['created'] !== []) {
            $io->section('Created parcels');
            $io->listing(array_map(
                static fn (array $parcel): string => sprintf('%s (%s)', $parcel['id'], $parcel['path']),
                $parcelResult['created'],
            ));
        }

        if ($parcelResult['updated'] !== []) {
            $io->section('Updated parcels');
            $io->listing(array_map(
                static fn (array $parcel): string => sprintf('%s path: "%s" -> "%s"', $parcel['id'], $parcel['from'], $parcel['to']),
                $parcelResult['updated'],
            ));
        }

        if ($parcelResult['deleted'] !== []) {
            $io->section('Deleted parcels');
            $io->listing(array_map(
                static function (array $parcel): string {
                    $references = array_sum($parcel['references']);
                    if ($references === 0) {
                        return sprintf('%s (%s)', $parcel['id'], $parcel['path']);
                    }

                    return sprintf('%s (%s) after clearing %d references', $parcel['id'], $parcel['path'], $references);
                },
                $parcelResult['deleted'],
            ));
        }

        if ($moduleResult['created'] !== []) {
            $io->section('Created modules');
            $io->listing(array_map(
                static fn (array $module): string => sprintf('%s (%s)', $module['id'], $module['package']),
                $moduleResult['created'],
            ));
        }

        if ($moduleResult['updated'] !== []) {
            $io->section('Updated modules');
            $io->listing(array_map(
                static fn (array $module): string => sprintf('%s %s: "%s" -> "%s"', $module['id'], $module['field'], $module['from'], $module['to']),
                $moduleResult['updated'],
            ));
        }

        if ($moduleResult['deleted'] !== []) {
            $io->section('Deleted modules');
            $io->listing(array_map(
                static function (array $module): string {
                    $details = sprintf('%s (%s)', $module['id'], $module['package']);
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

        if ($domainResult['created'] !== []) {
            $io->section('Created domains');
            $io->listing(array_map(
                static fn (array $domain): string => $domain['id'],
                $domainResult['created'],
            ));
        }

        if ($subdomainResult['created'] !== []) {
            $io->section('Created subdomains');
            $io->listing(array_map(
                static fn (array $subdomain): string => $subdomain['id'],
                $subdomainResult['created'],
            ));
        }

        if (
            $moduleResult['created'] === []
            && $moduleResult['updated'] === []
            && $moduleResult['deleted'] === []
            && $parcelResult['created'] === []
            && $parcelResult['updated'] === []
            && $parcelResult['deleted'] === []
            && $domainResult['created'] === []
            && $subdomainResult['created'] === []
        ) {
            $io->success('Parcel, module, domain, and subdomain rows already match installed configured-vendor packages.');

            return Command::SUCCESS;
        }

        $io->success('Parcel, module, domain, and subdomain rows synchronized from installed configured-vendor packages.');

        return Command::SUCCESS;
    }

    private function confirmRequired(InputInterface $input, SymfonyStyle $io, string $question, bool $default): bool
    {
        if (($_SERVER['APP_ENV'] ?? null) === 'test') {
            return $io->confirm($question, $default);
        }

        if (!$input->isInteractive() || !$this->hasInteractiveTerminal()) {
            throw new \RuntimeException(sprintf(
                'Confirmation is required for "%s", but stdin is not interactive. Run this command with an attached terminal; with Docker use: docker exec -it php bin/console symfonicat:schema:update',
                $question,
            ));
        }

        return $io->confirm($question, $default);
    }

    private function shouldAskForConfirmation(InputInterface $input): bool
    {
        if (($_SERVER['APP_ENV'] ?? null) === 'test') {
            return $input->isInteractive();
        }

        return $input->isInteractive() && $this->hasInteractiveTerminal();
    }

    private function hasInteractiveTerminal(): bool
    {
        return defined('STDIN') && function_exists('stream_isatty') && stream_isatty(STDIN);
    }
}
