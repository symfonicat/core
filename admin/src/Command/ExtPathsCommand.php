<?php

namespace Symfonicat\Command;

use Symfonicat\Service\PackageDiscoveryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'symfonicat:ext:paths',
    description: 'Output the flattened ext paths for all discovered Symfonicat packages.',
)]
final class ExtPathsCommand extends Command
{
    public function __construct(
        private readonly PackageDiscoveryService $packageDiscoveryService,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
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
            $relativeDirectory = $this->relativePath($directory);
            if ($relativeDirectory === '') {
                continue;
            }

            $relativeDirectories[] = $relativeDirectory;
        }

        return implode(' ', $relativeDirectories);
    }

    private function relativePath(string $path): string
    {
        $root = rtrim(str_replace('\\', '/', $this->projectDir), '/').'/';
        $path = str_replace('\\', '/', $path);

        if (str_starts_with($path, $root)) {
            return substr($path, strlen($root));
        }

        return $path;
    }
}
