<?php

namespace Symfonicat\Service;

final class PackageDiscoveryService
{
    private const SUPPORTED_ENTRY_TYPES = [
        'parcel',
        'module',
    ];

    public function __construct(
        private readonly string $subdomainDir,
    ) {
    }

    /**
     * @return list<array{
     *     installPath: string,
     *     name: string,
     *     package: string,
     *     vendor: string
     * }>
     */
    public function findSymfonicatPackages(): array
    {
        $packages = [];

        $rootComposerPath = $this->subdomainDir.'/composer.json';
        if (is_file($rootComposerPath)) {
            $package = $this->symfonicatPackageFromComposerFile($rootComposerPath, $this->subdomainDir);
            if ($package !== null) {
                $packages[$package['name']] = $package;
            }
        }

        $installedPath = $this->subdomainDir.'/vendor/composer/installed.json';
        if (!is_file($installedPath)) {
            return array_values($packages);
        }

        $installed = $this->decodeJsonFile($installedPath);
        $installedPackages = is_array($installed['packages'] ?? null) ? $installed['packages'] : [];

        foreach ($installedPackages as $package) {
            if (!is_array($package)) {
                continue;
            }

            $relativeInstallPath = trim((string) ($package['install-path'] ?? ''));
            if ($relativeInstallPath === '') {
                continue;
            }

            $installPath = realpath($this->subdomainDir.'/vendor/composer/'.$relativeInstallPath);
            if ($installPath === false || !is_dir($installPath)) {
                continue;
            }

            $composerPath = $installPath.'/composer.json';
            if (!is_file($composerPath)) {
                continue;
            }

            $packageData = $this->symfonicatPackageFromComposerFile($composerPath, $installPath);
            if ($packageData === null) {
                continue;
            }

            $packages[$packageData['name']] = $packageData;
        }

        foreach (glob($this->subdomainDir.'/vendor/*/*/composer.json') ?: [] as $composerPath) {
            $installPath = dirname($composerPath);
            $packageData = $this->symfonicatPackageFromComposerFile($composerPath, $installPath);
            if ($packageData === null) {
                continue;
            }

            $packages[$packageData['name']] = $packageData;
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
     *     packageName: string,
     *     vendor: string
     * }>
     */
    public function discoverEntryDirectories(string $type): array
    {
        if (!in_array($type, self::SUPPORTED_ENTRY_TYPES, true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported package entry type "%s".', $type));
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

                if ($type === 'domain' || $type === 'subdomain') {
                    $id = $name;
                } else {
                    $idPrefix = $package['vendor'] === 'core' ? 'core' : $package['vendor'].'/'.$package['package'];
                    $id = $idPrefix.'/'.$name;
                }

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
                    'vendor' => $package['vendor'],
                ];
            }
        }

        ksort($entries, SORT_STRING);

        return $entries;
    }

    /**
     * @return list<array{
     *     absolute: string,
     *     packageName: string,
     *     relative: string,
     *     type: string
     * }>
     */
    public function packageEntryBaseDirectories(string $type): array
    {
        if (!in_array($type, self::SUPPORTED_ENTRY_TYPES, true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported package entry type "%s".', $type));
        }

        $directories = [];

        foreach ($this->findSymfonicatPackages() as $package) {
            $absolute = $package['installPath'].'/assets/'.$type;
            $directories[] = [
                'absolute' => $absolute,
                'packageName' => $package['name'],
                'relative' => $this->relativePath($absolute),
                'type' => $type,
            ];
        }

        return $directories;
    }

    /**
     * @return array<string, array{
     *     buildDirectory: string,
     *     directory: string,
     *     id: string,
     *     module: string,
     *     modulePath: string,
     *     package: string,
     *     packageName: string,
     *     vendor: string
     * }>
     */
    public function discoverExtensions(): array
    {
        $extensions = [];

        foreach ($this->nativeGoRoots() as $root) {
            foreach ($this->childDirectories($root['directory']) as $directory) {
                $name = basename($directory);
                if ($name === '') {
                    continue;
                }

                $id = $this->relativePath($directory);
                $modulePath = $this->modulePathFromDirectory($directory);

                if (isset($extensions[$id])) {
                    if ($extensions[$id]['modulePath'] === $modulePath) {
                        continue;
                    }

                    throw new \RuntimeException(sprintf(
                        'Duplicate Symfonicat extension "%s" found in both "%s" and "%s".',
                        $id,
                        $extensions[$id]['packageName'],
                        $root['packageName'],
                    ));
                }

                $extensions[$id] = [
                    'buildDirectory' => $directory,
                    'directory' => $directory,
                    'id' => $id,
                    'module' => $name,
                    'modulePath' => $modulePath,
                    'package' => $root['package'],
                    'packageName' => $root['packageName'],
                    'vendor' => $root['vendor'],
                ];
            }
        }

        ksort($extensions, SORT_STRING);

        return array_values($extensions);
    }

    /**
     * @return list<string>
     */
    private function childDirectories(string $baseDirectory): array
    {
        if (!is_dir($baseDirectory)) {
            return [];
        }

        $directories = glob(rtrim($baseDirectory, '/').'/*', GLOB_ONLYDIR) ?: [];
        sort($directories, SORT_STRING);

        return $directories;
    }

    /**
     * @return array<string, array{
     *     directory: string,
     *     entry: string,
     *     id: string,
     *     package: string,
     *     packageName: string,
     *     path: string,
     *     type: string,
     *     vendor: string
     * }>
     */
    public function discoverParcels(): array
    {
        $parcels = [];

        foreach ($this->discoverEntryDirectories('parcel') as $parcelId => $entry) {
            $parcels[$parcelId] = [
                'directory' => $entry['directory'],
                'entry' => $entry['entry'],
                'id' => $parcelId,
                'package' => $entry['package'],
                'packageName' => $entry['packageName'],
                'path' => $this->relativePath($entry['directory']),
                'type' => 'parcel',
                'vendor' => $entry['vendor'],
            ];
        }

        ksort($parcels, SORT_STRING);

        return $parcels;
    }

    /**
     * @return array<string, array{
     *     directory: string,
     *     entry: string,
     *     id: string,
     *     name: string,
     *     package: string,
     *     packageName: string,
     *     vendor: string
     * }>
     */
    public function discoverModules(): array
    {
        $modules = [];

        foreach ($this->discoverEntryDirectories('module') as $id => $module) {
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

    private function vendorName(string $packageName): string
    {
        $parts = explode('/', $packageName, 2);

        return trim($parts[0] ?? '');
    }

    /**
     * @return array{
     *     installPath: string,
     *     name: string,
     *     package: string,
     *     vendor: string
     * }|null
     */
    private function symfonicatPackageFromComposerFile(string $composerPath, string $installPath): ?array
    {
        $composer = $this->decodeJsonFile($composerPath);
        $extra = is_array($composer['extra'] ?? null) ? $composer['extra'] : [];
        if (($extra['symfonicat'] ?? false) !== true) {
            return null;
        }

        $packageName = trim((string) ($composer['name'] ?? ''));
        if ($packageName === '') {
            return null;
        }

        return [
            'installPath' => $installPath,
            'name' => $packageName,
            'package' => $this->shortPackageName($packageName),
            'vendor' => $this->vendorName($packageName),
        ];
    }

    private function modulePathFromDirectory(string $directory): string
    {
        $composerPath = $directory.'/go.mod';
        if (!is_file($composerPath)) {
            throw new \RuntimeException(sprintf('Go directory "%s" is missing go.mod.', $directory));
        }

        $contents = file_get_contents($composerPath);
        if ($contents === false) {
            throw new \RuntimeException(sprintf('Unable to read "%s".', $composerPath));
        }

        foreach (preg_split('~\R~', $contents) ?: [] as $line) {
            $line = trim($line);
            if (!str_starts_with($line, 'module ')) {
                continue;
            }

            $modulePath = trim(substr($line, 7));
            if ($modulePath === '') {
                break;
            }

            return $modulePath;
        }

        throw new \RuntimeException(sprintf('Go directory "%s" has a go.mod without a module path.', $directory));
    }

    /**
     * @return list<array{
     *     directory: string,
     *     package: string,
     *     packageName: string,
     *     vendor: string
     * }>
     */
    private function nativeGoRoots(): array
    {
        $roots = [
            [
                'directory' => $this->subdomainDir.'/native/go',
                'package' => 'core',
                'packageName' => 'symfonicat/core',
                'vendor' => 'core',
            ],
            [
                'directory' => $this->subdomainDir.'/core/native/go',
                'package' => 'core',
                'packageName' => 'symfonicat/core',
                'vendor' => 'core',
            ],
        ];

        foreach ($this->findSymfonicatPackages() as $package) {
            $roots[] = [
                'directory' => $package['installPath'].'/native/go',
                'package' => $package['package'],
                'packageName' => $package['name'],
                'vendor' => $package['vendor'],
            ];
        }

        return array_values(array_filter($roots, static fn (array $root): bool => is_dir($root['directory'])));
    }

    private function relativePath(string $path): string
    {
        $root = rtrim(str_replace('\\', '/', $this->subdomainDir), '/').'/';
        $path = str_replace('\\', '/', $path);

        if (str_starts_with($path, $root)) {
            return substr($path, strlen($root));
        }

        return $path;
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

        $decoded = $this->decodeJsonValue($contents);
        if (!is_array($decoded)) {
            throw new \RuntimeException(sprintf('JSON file "%s" does not contain valid JSON.', $path));
        }

        return $decoded;
    }

    /**
     * @return mixed
     */
    private function decodeJsonValue(string $contents)
    {
        try {
            return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('JSON file does not contain valid JSON.', 0, $exception);
        }
    }
}
