<?php

namespace Symfonicat\Command;

use Symfonicat\Service\PackageDiscoveryService;
use Symfonicat\Service\RuntimeConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'symfonicat:data:webpack',
    description: 'Output parcel and module package entry data for webpack.',
)]
final class WebpackModulesDataCommand extends Command
{
    public function __construct(
        private readonly PackageDiscoveryService $packageDiscoveryService,
        private readonly RuntimeConfig $runtimeConfig,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(json_encode([
            'parcels' => $this->parcelEntriesFromRepositoryOrPackages(),
            'modules' => $this->entriesFromRepositoryOrPackages(
                fn (): array => $this->runtimeConfig->modules(),
                'module',
            ),
        ], JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }

    /**
     * @return list<array{
     *     entry: string,
     *     id: string,
     *     package: string,
     *     packageName: string
     * }>
     */
    private function parcelEntriesFromRepositoryOrPackages(): array
    {
        $packageEntries = $this->packageDiscoveryService->discoverParcels();

        try {
            $resolvedEntries = [];

            foreach ($this->runtimeConfig->parcels() as $parcel) {
                $id = trim((string) $parcel->getId());
                $path = trim((string) $parcel->getPath());
                if ($id === '' || $path === '') {
                    continue;
                }

                $entry = $this->resolveEntryPath($path);
                if ($entry === null) {
                    continue;
                }

                $packageEntry = $packageEntries[$id] ?? null;
                $resolvedEntries[] = [
                    'entry' => $entry,
                    'id' => $id,
                    'package' => $packageEntry['package'] ?? $this->packageFromId($id),
                    'packageName' => $packageEntry['packageName'] ?? 'manual',
                ];
            }

            usort($resolvedEntries, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);

            return $resolvedEntries;
        } catch (\Throwable) {
            return $this->entriesFromPackages($packageEntries);
        }
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

    private function resolveEntryPath(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        $absolutePath = str_starts_with($path, '/') ? $path : rtrim($this->projectDir, '/').'/'.$path;
        if (is_dir($absolutePath)) {
            $absolutePath = rtrim($absolutePath, '/').'/index.js';
        }

        return is_file($absolutePath) ? $absolutePath : null;
    }

    private function packageFromId(string $id): string
    {
        $parts = explode('/', $id);

        return $parts[1] ?? $parts[0] ?? 'core';
    }
}
