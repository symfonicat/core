<?php

namespace Symfonicat\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class NativeDiscoveryService
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
    public function discoverPaths(string $type, string $pattern): array
    {
        $paths = [];

        foreach ($this->discoverPackageRoots($pattern) as $packageRoot) {
            foreach ($this->candidateNativeDirectories($packageRoot['absolute'], $type) as $directory) {
                foreach ($this->childDirectories($directory) as $childDirectory) {
                    $paths[$this->relativePath($childDirectory)] = $this->relativePath($childDirectory);
                }
            }
        }

        ksort($paths, SORT_STRING);

        return array_values($paths);
    }

    /**
     * @return list<string>
     */
    public function discoverNames(string $type, string $pattern): array
    {
        return array_values(array_map(
            static fn (string $path): string => basename($path),
            $this->discoverPaths($type, $pattern),
        ));
    }

    /**
     * @return list<array{absolute: string, alias: string}>
     */
    private function discoverPackageRoots(string $pattern): array
    {
        $matches = [];

        foreach ($this->builtInPackageRoots() as $packageRoot) {
            if ($this->patternMatches($pattern, $packageRoot['alias'])) {
                $matches[] = $packageRoot;
            }
        }

        foreach ($this->packageDiscoveryService->findSymfonicatPackages() as $package) {
            $alias = $this->relativePath($package['installPath']).'/';
            if (!$this->patternMatches($pattern, $alias)) {
                continue;
            }

            $matches[] = [
                'absolute' => $package['installPath'],
                'alias' => $alias,
            ];
        }

        return $matches;
    }

    /**
     * @return list<array{absolute: string, alias: string}>
     */
    private function builtInPackageRoots(): array
    {
        return [
            [
                'absolute' => rtrim($this->projectDir, '/'),
                'alias' => './',
            ],
            [
                'absolute' => rtrim($this->projectDir, '/').'/core',
                'alias' => 'core/',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function candidateNativeDirectories(string $packageRoot, string $type): array
    {
        $directories = [];

        foreach ([
            rtrim($packageRoot, '/').'/native/'.$type,
        ] as $directory) {
            if (is_dir($directory)) {
                $directories[] = $directory;
            }
        }

        return array_values(array_unique($directories));
    }

    /**
     * @return list<string>
     */
    private function childDirectories(string $directory): array
    {
        $children = glob(rtrim($directory, '/').'/*', GLOB_ONLYDIR) ?: [];
        sort($children, SORT_STRING);

        return $children;
    }

    private function patternMatches(string $pattern, string $candidate): bool
    {
        $pattern = trim($pattern);
        if ($pattern === '' || $pattern === '.' || $pattern === './') {
            return $candidate === './';
        }

        $regex = $this->globToRegex($pattern);

        return preg_match($regex, $candidate) === 1;
    }

    private function globToRegex(string $pattern): string
    {
        $quoted = preg_quote(trim($pattern), '#');
        $quoted = str_replace('\*\*', '.*', $quoted);
        $quoted = str_replace('\*', '[^/]*', $quoted);
        $quoted = str_replace('\?', '.', $quoted);

        return '#^'.rtrim($quoted, '/').'/?$#';
    }

    private function relativePath(string $path): string
    {
        $root = rtrim(str_replace('\\', '/', $this->projectDir), '/').'/';
        $path = str_replace('\\', '/', $path);

        if (str_starts_with($path, $root)) {
            return ltrim(substr($path, strlen($root)), '/');
        }

        return ltrim($path, '/');
    }
}
