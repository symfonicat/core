<?php

if (!function_exists('module_routes_polyfill_package_is_symfonicat')) {
    function module_routes_polyfill_package_is_symfonicat(array $composer): bool
    {
        if (!isset($composer['extra']) || !is_array($composer['extra'])) {
            return false;
        }

        if (($composer['extra']['symfonicat'] ?? false) !== true) {
            return false;
        }

        return isset($composer['name']);
    }
}

if (!function_exists('module_routes_polyfill_add_package')) {
    function module_routes_polyfill_add_package(array &$packages, array $composer, string $installPath): void
    {
        $packages[] = [
            'composer' => $composer,
            'installPath' => $installPath,
        ];
    }
}

if (!function_exists('module_routes_polyfill_collect_root_package')) {
    function module_routes_polyfill_collect_root_package(string $projectDir, array &$packages): void
    {
        $rootComposerFile = $projectDir.'/composer.json';
        if (!is_file($rootComposerFile)) {
            return;
        }

        $rootComposer = json_decode((string) file_get_contents($rootComposerFile), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($rootComposer) || !module_routes_polyfill_package_is_symfonicat($rootComposer)) {
            return;
        }

        module_routes_polyfill_add_package($packages, $rootComposer, $projectDir);
    }
}

if (!function_exists('module_routes_polyfill_collect_installed_packages')) {
    function module_routes_polyfill_collect_installed_packages(string $projectDir, array &$packages): void
    {
        $installedFile = $projectDir.'/vendor/composer/installed.json';
        if (!is_file($installedFile)) {
            return;
        }

        $installed = json_decode((string) file_get_contents($installedFile), true, 512, JSON_THROW_ON_ERROR);
        $installedPackages = is_array($installed['packages'] ?? null) ? $installed['packages'] : [];

        foreach ($installedPackages as $package) {
            if (!is_array($package)) {
                continue;
            }

            if (($package['extra']['symfonicat'] ?? false) !== true) {
                continue;
            }

            $relativeInstallPath = trim((string) ($package['install-path'] ?? ''));
            if ($relativeInstallPath === '') {
                continue;
            }

            $installPath = realpath($projectDir.'/vendor/composer/'.$relativeInstallPath);
            if ($installPath === false || !is_dir($installPath)) {
                continue;
            }

            $composerPath = $installPath.'/composer.json';
            if (!is_file($composerPath)) {
                continue;
            }

            $composer = json_decode((string) file_get_contents($composerPath), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($composer) || !isset($composer['name'])) {
                continue;
            }

            module_routes_polyfill_add_package($packages, $composer, $installPath);
        }
    }
}

if (!function_exists('module_routes_collect_packages')) {
    function module_routes_collect_packages(string $projectDir): array
    {
        $packages = [];
        module_routes_polyfill_collect_root_package($projectDir, $packages);
        module_routes_polyfill_collect_installed_packages($projectDir, $packages);

        return $packages;
    }
}

if (!function_exists('module_routes_polyfill_add_scan_target')) {
    function module_routes_polyfill_add_scan_target(array &$scanTargets, string $directory, string $classPrefix, string $packageName): void
    {
        $scanTargets[$directory.'|'.$classPrefix] = [
            'classPrefix' => $classPrefix,
            'directory' => $directory,
            'packageName' => $packageName,
        ];
    }
}

if (!function_exists('module_routes_polyfill_collect_scan_targets_from_package')) {
    function module_routes_polyfill_collect_scan_targets_from_package(array $packageInfo, array &$scanTargets): void
    {
        $composer = $packageInfo['composer'] ?? null;
        $installPath = $packageInfo['installPath'] ?? null;

        if (!is_array($composer) || !is_string($installPath)) {
            return;
        }

        $autoload = is_array($composer['autoload']['psr-4'] ?? null) ? $composer['autoload']['psr-4'] : [];

        $directModuleTargets = [];
        $fallbackModuleTargets = [];

        foreach ($autoload as $prefix => $paths) {
            $paths = is_array($paths) ? $paths : [$paths];

            foreach ($paths as $path) {
                $baseDir = realpath($installPath.'/'.trim((string) $path, '/'));
                if ($baseDir === false || !is_dir($baseDir)) {
                    continue;
                }

                if (basename($baseDir) === 'Module') {
                    $directModuleTargets[$baseDir] = [
                        'classPrefix' => (string) $prefix,
                        'directory' => $baseDir,
                    ];

                    continue;
                }

                $moduleDir = $baseDir.'/Module';
                if (is_dir($moduleDir)) {
                    $fallbackModuleTargets[$moduleDir] = [
                        'classPrefix' => (string) $prefix.'Module\\',
                        'directory' => $moduleDir,
                    ];
                }
            }
        }

        foreach ($directModuleTargets as $directory => $target) {
            module_routes_polyfill_add_scan_target(
                $scanTargets,
                $directory,
                $target['classPrefix'],
                (string) $composer['name']
            );
        }

        foreach ($fallbackModuleTargets as $directory => $target) {
            if (isset($directModuleTargets[$directory])) {
                continue;
            }

            module_routes_polyfill_add_scan_target(
                $scanTargets,
                $directory,
                $target['classPrefix'],
                (string) $composer['name']
            );
        }
    }
}

if (!function_exists('module_routes_collect_scan_targets')) {
    function module_routes_collect_scan_targets(array $packages): array
    {
        $scanTargets = [];

        foreach ($packages as $packageInfo) {
            if (!is_array($packageInfo)) {
                continue;
            }

            module_routes_polyfill_collect_scan_targets_from_package($packageInfo, $scanTargets);
        }

        return $scanTargets;
    }
}
