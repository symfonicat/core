<?php

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfonicat\Attribute\Module;
use Symfonicat\Attribute\ModuleRoute;

require_once __DIR__.'/polyfill.php';

$routes = new RouteCollection();
$projectDir = dirname(__DIR__);

$packages = module_routes_collect_packages($projectDir);
if (!is_array($packages)) {
    $packages = [];
}

$scanTargets = module_routes_collect_scan_targets($packages);
if (!is_array($scanTargets)) {
    $scanTargets = [];
}

foreach ($scanTargets as $target) {
    if (
        !is_array($target)
        || !isset($target['directory'], $target['classPrefix'], $target['packageName'])
        || !is_string($target['directory'])
        || !is_string($target['classPrefix'])
        || !is_string($target['packageName'])
    ) {
        continue;
    }

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

            $routeName = sprintf('symfonicat_module_%s_%s', $packageSlug, $method->getName());
            $route = new Route(
                $basePath.'/'.$method->getName(),
                ['_controller' => $className.'::'.$method->getName()],
                methods: ['POST'],
            );

            $routes->add($routeName, $route);
        }
    }
}

return $routes;
