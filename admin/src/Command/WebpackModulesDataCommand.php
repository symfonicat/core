<?php

namespace Symfonicat\Command;

use Symfonicat\Service\PackageDiscoveryService;
use Symfonicat\Service\RuntimeConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'symfonicat:data:webpack',
    description: 'Output domain, subdomain, and module package entry data for webpack.',
)]
final class WebpackModulesDataCommand extends Command
{
    public function __construct(
        private readonly PackageDiscoveryService $packageDiscoveryService,
        private readonly RuntimeConfig $runtimeConfig,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(json_encode([
            'modules' => $this->entriesFromRepositoryOrPackages(
                fn (): array => $this->runtimeConfig->modules(),
                'modules',
            ),
            'subdomains' => $this->entriesFromRepositoryOrPackages(
                fn (): array => $this->runtimeConfig->subdomains(),
                'subdomains',
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
