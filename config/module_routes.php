<?php

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfonicat\Attribute\Module;
use Symfonicat\Attribute\ModuleRoute;

$routes = new RouteCollection();
$projectDir = dirname(__DIR__);

$packages = [];

$rootComposerFile = $projectDir.'/composer.json';
if (is_file($rootComposerFile)) {
    $rootComposer = symfonicat_json_decode((string) file_get_contents($rootComposerFile));
    if (is_array($rootComposer) && ($rootComposer['extra']['symfonicat'] ?? false) === true && isset($rootComposer['name'])) {
        $packages[] = [
            'composer' => $rootComposer,
            'installPath' => $projectDir,
        ];
    }
}

$installedFile = $projectDir.'/vendor/composer/installed.json';
if (is_file($installedFile)) {
    $installed = symfonicat_json_decode((string) file_get_contents($installedFile));
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

        $composer = symfonicat_json_decode((string) file_get_contents($composerPath));
        if (!is_array($composer) || !isset($composer['name'])) {
            continue;
        }

        $packages[] = [
            'composer' => $composer,
            'installPath' => $installPath,
        ];
    }
}

$scanTargets = [];
foreach ($packages as $packageInfo) {
    $composer = $packageInfo['composer'];
    $installPath = $packageInfo['installPath'];
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
        $scanTargets[$directory.'|'.$target['classPrefix']] = [
            'classPrefix' => $target['classPrefix'],
            'directory' => $directory,
            'packageName' => (string) $composer['name'],
        ];
    }

    foreach ($fallbackModuleTargets as $directory => $target) {
        if (isset($directModuleTargets[$directory])) {
            continue;
        }

        $scanTargets[$directory.'|'.$target['classPrefix']] = [
            'classPrefix' => $target['classPrefix'],
            'directory' => $directory,
            'packageName' => (string) $composer['name'],
        ];
    }
}

foreach ($scanTargets as $target) {
    $directory = $target['directory'];
    $classPrefix = $target['classPrefix'];
    $packageName = $target['packageName'];
    $packageSlug = str_replace(['/', '-'], '_', $packageName);

    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
            continue;
        }

        $file = $fileInfo->getPathname();

        $relativePath = substr($file, strlen($directory) + 1);
        if ($relativePath === false) {
            continue;
        }

        $className = $classPrefix.str_replace(['/', '.php'], ['\\', ''], $relativePath);
        if (!class_exists($className)) {
            continue;
        }

        $reflectionClass = new \ReflectionClass($className);
        if ($reflectionClass->isAbstract()) {
            continue;
        }

        $moduleRouteAttribute = $reflectionClass->getAttributes(ModuleRoute::class)[0] ?? null;
        if (!$moduleRouteAttribute) {
            continue;
        }

        /** @var ModuleRoute $moduleRoute */
        $moduleRoute = $moduleRouteAttribute->newInstance();
        $moduleRouteArguments = $moduleRouteAttribute->getArguments();
        $basePath = array_key_exists('path', $moduleRouteArguments) || array_key_exists(0, $moduleRouteArguments)
            ? rtrim((string) $moduleRoute->path, '/')
            : '/m/'.$packageName;

        foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic()) {
                continue;
            }

            $moduleAttribute = $method->getAttributes(Module::class)[0] ?? null;
            if (!$moduleAttribute) {
                continue;
            }

            /** @var Module $module */
            $module = $moduleAttribute->newInstance();
            $methods = ['POST'];

            $routeName = sprintf('symfonicat_module_%s_%s', $packageSlug, $method->getName());
            $route = new Route(
                $basePath.'/'.$method->getName(),
                ['_controller' => $className.'::'.$method->getName()],
                methods: $methods,
            );

            $routes->add($routeName, $route);
        }
    }
}

return $routes;
