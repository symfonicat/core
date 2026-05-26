<?php

namespace Symfonicat\Service;

final class PackageDiscoveryService
{
    private const SUPPORTED_ENTRY_TYPES = [
        'parcel',
        'domain',
        'endpoint',
        'module',
        'subdomain',
    ];

    /**
     * @param list<string> $vendors
     */
    public function __construct(
        private readonly string $subdomainDir,
        private readonly array $vendors = ['symfonicat'],
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
            $rootComposer = $this->decodeJsonFile($rootComposerPath);
            $packageName = trim((string) ($rootComposer['name'] ?? 'symfonicat/core'));
            if ($packageName !== '' && $this->isConfiguredVendorPackage($packageName)) {
                $packages[$packageName] = [
                    'installPath' => $this->subdomainDir,
                    'name' => $packageName,
                    'package' => $this->shortPackageName($packageName),
                    'vendor' => 'core',
                ];
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

            $packageName = trim((string) ($package['name'] ?? ''));
            $relativeInstallPath = trim((string) ($package['install-path'] ?? ''));
            if ($packageName === '' || $relativeInstallPath === '' || !$this->isConfiguredVendorPackage($packageName)) {
                continue;
            }

            $installPath = realpath($this->subdomainDir.'/vendor/composer/'.$relativeInstallPath);
            if ($installPath === false || !is_dir($installPath)) {
                continue;
            }

            $packages[$packageName] = [
                'installPath' => $installPath,
                'name' => $packageName,
                'package' => $this->shortPackageName($packageName),
                'vendor' => $this->vendorName($packageName),
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

                if ($type === 'domain' || $type === 'endpoint' || $type === 'subdomain') {
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

    private function relativePath(string $path): string
    {
        $root = rtrim(str_replace('\\', '/', $this->subdomainDir), '/').'/';
        $path = str_replace('\\', '/', $path);

        if (str_starts_with($path, $root)) {
            return substr($path, strlen($root));
        }

        return $path;
    }

    private function isConfiguredVendorPackage(string $packageName): bool
    {
        $vendor = $this->vendorName($packageName);

        return $vendor !== '' && in_array($vendor, $this->normalizedVendors(), true);
    }

    /**
     * @return list<string>
     */
    private function normalizedVendors(): array
    {
        $vendors = array_values(array_unique(array_filter(array_map(
            static fn (mixed $vendor): string => trim((string) $vendor),
            $this->vendors,
        ))));

        return $vendors === [] ? ['symfonicat'] : $vendors;
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
