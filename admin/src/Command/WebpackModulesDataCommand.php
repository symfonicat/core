<?php

namespace Symfonicat\Command;

use Symfonicat\Repository\ApplicationRepository;
use Symfonicat\Repository\DomainRepository;
use Symfonicat\Repository\ModuleRepository;
use Symfonicat\Repository\ProjectRepository;
use Symfonicat\Service\PackageDiscoveryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'symfonicat:data:webpack',
    description: 'Output application, domain, project, and module package entry data for webpack.',
)]
final class WebpackModulesDataCommand extends Command
{
    public function __construct(
        private readonly ApplicationRepository $applicationRepository,
        private readonly ModuleRepository $moduleRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly PackageDiscoveryService $packageDiscoveryService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(json_encode([
            'applications' => $this->entriesFromRepositoryOrPackages(
                fn (): array => $this->applicationRepository->findAll(),
                'applications',
            ),
            'modules' => $this->entriesFromRepositoryOrPackages(
                fn (): array => $this->moduleRepository->findAll(),
                'modules',
            ),
            'projects' => $this->entriesFromRepositoryOrPackages(
                fn (): array => $this->projectRepository->findAll(),
                'projects',
            ),
        ], JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }

    /**
     * @param callable(): iterable<object> $loader
     *
     * @return list<array{
     *     entry: string,
     *     id: string,
     *     package: string,
     *     packageName: string
     * }>
     */
    private function entriesFromRepositoryOrPackages(callable $loader, string $type): array
    {
        $entries = $this->packageDiscoveryService->discoverEntryDirectories($type);

        try {
            $ids = [];

            foreach ($loader() as $entity) {
                if (!method_exists($entity, 'getId')) {
                    continue;
                }

                $id = trim((string) $entity->getId());
                if ($id !== '') {
                    $ids[] = $id;
                }
            }

            $ids = array_values(array_unique($ids));
            sort($ids, SORT_STRING);

            $resolvedEntries = [];

            foreach ($ids as $id) {
                $entry = $entries[$id] ?? null;
                if ($entry === null || !is_file($entry['entry'])) {
                    continue;
                }

                $resolvedEntries[] = [
                    'entry' => $entry['entry'],
                    'id' => $entry['id'],
                    'package' => $entry['package'],
                    'packageName' => $entry['packageName'],
                ];
            }

            return $resolvedEntries;
        } catch (\Throwable) {
            return $this->entriesFromPackages($entries);
        }
    }

    /**
     * @param array<string, array{
     *     directory: string,
     *     entry: string,
     *     id: string,
     *     package: string,
     *     packageName: string
     * }> $entries
     *
     * @return list<array{
     *     entry: string,
     *     id: string,
     *     package: string,
     *     packageName: string
     * }>
     */
    private function entriesFromPackages(array $entries): array
    {
        $resolvedEntries = [];

        foreach ($entries as $entry) {
            if (!is_file($entry['entry'])) {
                continue;
            }

            $resolvedEntries[] = [
                'entry' => $entry['entry'],
                'id' => $entry['id'],
                'package' => $entry['package'],
                'packageName' => $entry['packageName'],
            ];
        }

        return $resolvedEntries;
    }
}
