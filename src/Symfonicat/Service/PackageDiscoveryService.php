<?php

namespace Symfonicat\Service;

final class PackageDiscoveryService
{
    private const SUPPORTED_ENTRY_TYPES = [
        'applications',
        'domains',
        'modules',
        'projects',
    ];

    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    /**
     * @return list<array{
     *     installPath: string,
     *     name: string,
     *     package: string
     * }>
     */
    public function findSymfonicatPackages(): array
    {
        $packages = [];

        $rootComposerPath = $this->projectDir.'/composer.json';
        if (is_file($rootComposerPath)) {
            $rootComposer = $this->decodeJsonFile($rootComposerPath);
            $packageName = trim((string) ($rootComposer['name'] ?? 'symfonicat/core'));
            if (str_starts_with($packageName, 'symfonicat/')) {
                $packages[$packageName] = [
                    'installPath' => $this->projectDir,
                    'name' => $packageName,
                    'package' => $this->shortPackageName($packageName),
                ];
            }
        }

        $installedPath = $this->projectDir.'/vendor/composer/installed.json';
        if (!is_file($installedPath)) {
            return array_values($packages);
        }

        $installed = $this->decodeJsonFile($installedPath);
        $installedPackages = is_array($installed['packages'] ?? null) ? $installed['packages'] : [];

        foreach ($installedPackages as $package) {
            if (!is_array($package)) {
                continue;
            }

            $packageName = trim((string) ($package['name'] ?? ''));
            $relativeInstallPath = trim((string) ($package['install-path'] ?? ''));
            if ($packageName === '' || $relativeInstallPath === '' || !str_starts_with($packageName, 'symfonicat/')) {
                continue;
            }

            $installPath = realpath($this->projectDir.'/vendor/composer/'.$relativeInstallPath);
            if ($installPath === false || !is_dir($installPath)) {
                continue;
            }

            $packages[$packageName] = [
                'installPath' => $installPath,
                'name' => $packageName,
                'package' => $this->shortPackageName($packageName),
            ];
        }

        ksort($packages, SORT_STRING);

        return array_values($packages);
    }

    /**
     * @return array<string, array{
     *     directory: string,
     *     entry: string,
     *     id: string,
     *     package: string,
     *     packageName: string
     * }>
     */
    public function discoverEntryDirectories(string $type): array
    {
        if (!in_array($type, self::SUPPORTED_ENTRY_TYPES, true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported Symfonicat package entry type "%s".', $type));
        }

        $entries = [];

        foreach ($this->findSymfonicatPackages() as $package) {
            $baseDirectory = $package['installPath'].'/assets/'.$type;
            $directories = glob($baseDirectory.'/*', GLOB_ONLYDIR) ?: [];
            sort($directories, SORT_STRING);

            foreach ($directories as $directory) {
                $name = basename($directory);
                if ($name === '') {
                    continue;
                }

                // Use package-prefixed ids so database ids like "analytics/main" match discovered entries.
                $id = $package['package'].'/'.$name;

                if (isset($entries[$id])) {
                    throw new \RuntimeException(sprintf(
                        'Duplicate Symfonicat %s entry "%s" found in both "%s" and "%s".',
                        $type,
                        $id,
                        $entries[$id]['packageName'],
                        $package['name'],
                    ));
                }

                $entries[$id] = [
                    'directory' => $directory,
                    'entry' => $directory.'/index.js',
                    'id' => $id,
                    'package' => $package['package'],
                    'packageName' => $package['name'],
                ];
            }
        }

        ksort($entries, SORT_STRING);

        return $entries;
    }

    /**
     * @return array<string, array{
     *     directory: string,
     *     entry: string,
     *     id: string,
     *     name: string,
     *     package: string,
     *     packageName: string
     * }>
     */
    public function discoverModules(): array
    {
        $modules = [];

        foreach ($this->discoverEntryDirectories('modules') as $id => $module) {
            $packagePath = $module['directory'].'/package.json';
            if (!is_file($packagePath)) {
                throw new \RuntimeException(sprintf('Module "%s" is missing "%s".', $id, $packagePath));
            }

            $packageJson = $this->decodeJsonFile($packagePath);
            $name = trim((string) ($packageJson['name'] ?? ''));
            if ($name === '') {
                throw new \RuntimeException(sprintf('Module package "%s" must define a non-empty "name".', $packagePath));
            }

            $modules[$id] = $module + ['name' => $name];
        }

        return $modules;
    }

    private function shortPackageName(string $packageName): string
    {
        $parts = explode('/', $packageName);

        return trim((string) end($parts));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonFile(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException(sprintf('Unable to read JSON file "%s".', $path));
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException(sprintf('JSON file "%s" does not contain valid JSON.', $path), 0, $exception);
        }

        return is_array($decoded) ? $decoded : [];
    }
}
