<?php

namespace Symfonicat\Command;

use Symfonicat\Service\PackageDiscoveryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'symfonicat:ext:list',
    description: 'Output the flattened ext names for all discovered Symfonicat packages.',
)]
final class ExtListCommand extends Command
{
    public function __construct(
        private readonly PackageDiscoveryService $packageDiscoveryService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln($this->masterManifest());

        return Command::SUCCESS;
    }

    private function masterManifest(): string
    {
        $manifests = [];

        foreach ($this->packageDiscoveryService->findSymfonicatPackages() as $package) {
            $manifest = $this->extManifestForPackage($package['installPath']);
            if ($manifest === '') {
                continue;
            }

            $manifests[] = $manifest;
        }

        return implode(' ', $manifests);
    }

    private function extManifestForPackage(string $installPath): string
    {
        $extDirectory = rtrim($installPath, '/').'/ext';
        if (!is_dir($extDirectory)) {
            return '';
        }

        $directories = glob($extDirectory.'/*', GLOB_ONLYDIR) ?: [];
        sort($directories, SORT_STRING);

        $relativeDirectories = [];
        foreach ($directories as $directory) {
            $relativeDirectory = basename($directory);
            if ($relativeDirectory === '') {
                continue;
            }

            $relativeDirectories[] = $relativeDirectory;
        }

        return implode(' ', $relativeDirectories);
    }
}
