<?php

namespace Symfonicat\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ScriptlingService
{
    public function __construct(
        private readonly PackageDiscoveryService $packageDiscoveryService,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * @return list<string>
     */
    public function copyScriptLines(): array
    {
        $lines = [];
        $lines[] = 'set -eu';

        foreach ($this->packageDiscoveryService->discoverExtensions() as $extension) {
            if ($this->isRootExtension($extension['directory'])) {
                continue;
            }

            $target = $this->targetDirectory($extension['id']);
            $lines[] = sprintf('mkdir -p %s', dirname($target));
            $lines[] = sprintf('rm -rf %s', $target);
            $lines[] = sprintf('cp -R %s %s', $extension['directory'], $target);
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    public function xcaddyFlags(): array
    {
        $flags = [];

        foreach ($this->packageDiscoveryService->discoverExtensions() as $extension) {
            $flags[] = sprintf(
                '--with %s=%s',
                $extension['modulePath'],
                $extension['buildDirectory'],
            );
        }

        return $flags;
    }

    private function isRootExtension(string $directory): bool
    {
        $root = rtrim(str_replace('\\', '/', $this->projectDir), '/').'/';
        $directory = str_replace('\\', '/', $directory);

        return str_starts_with($directory, $root.'extensions/');
    }

    private function targetDirectory(string $id): string
    {
        return '/symfonicat/extensions/'.$id;
    }
}
