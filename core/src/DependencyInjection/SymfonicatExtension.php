<?php

namespace Symfonicat\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfonicat\Service\PackageDiscoveryService;

final class SymfonicatExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        $root = dirname(__DIR__, 3);

        $container->prependExtensionConfig('twig', [
            'paths' => [
                $root.'/templates',
                $root.'/core/templates' => 'symfonicat',
            ],
        ]);

        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'Symfonicat' => [
                        'is_bundle' => false,
                        'type' => 'attribute',
                        'dir' => $root.'/core/src/Entity',
                        'prefix' => 'Symfonicat\\Entity',
                        'alias' => 'Symfonicat',
                    ],
                ],
            ],
        ]);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $this->processConfiguration($configuration, $configs);

        $root = dirname(__DIR__, 3);
        $loader = new YamlFileLoader($container, new FileLocator($root.'/config'));
        $loader->load('services.yaml');

        $packageDiscoveryService = new PackageDiscoveryService($root);
        foreach ($packageDiscoveryService->findSymfonicatPackages() as $package) {
            if ($package['installPath'] === $root) {
                continue;
            }

            $serviceConfig = $package['installPath'].'/config/services.yaml';
            if (!is_file($serviceConfig)) {
                continue;
            }

            $packageLoader = new YamlFileLoader($container, new FileLocator(dirname($serviceConfig)));
            $packageLoader->load(basename($serviceConfig));
        }

        $this->publicizeModuleActionDependencies($container, $root);
    }

    private function publicizeModuleActionDependencies(ContainerBuilder $container, string $root): void
    {
        if (
            !function_exists('module_routes_collect_packages')
            || !function_exists('module_routes_collect_scan_targets')
        ) {
            return;
        }

        $packages = module_routes_collect_packages($root);
        if (!is_array($packages)) {
            return;
        }

        $scanTargets = module_routes_collect_scan_targets($packages);
        if (!is_array($scanTargets)) {
            return;
        }

        foreach ($scanTargets as $target) {
            if (
                !is_array($target)
                || !isset($target['directory'], $target['classPrefix'])
                || !is_string($target['directory'])
                || !is_string($target['classPrefix'])
            ) {
                continue;
            }

            $directory = $target['directory'];
            $classPrefix = $target['classPrefix'];
            if (!is_dir($directory)) {
                continue;
            }

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

                if (!$reflectionClass->getAttributes(\Symfonicat\Attribute\ModuleRoute::class)) {
                    continue;
                }

                foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                    if ($method->isStatic() || !$method->getAttributes(\Symfonicat\Attribute\Module::class)) {
                        continue;
                    }

                    foreach ($method->getParameters() as $parameter) {
                        $this->publicizeParameterType($container, $parameter->getType());
                    }
                }
            }
        }
    }

    private function publicizeParameterType(ContainerBuilder $container, ?\ReflectionType $type): void
    {
        if ($type instanceof \ReflectionNamedType) {
            if ($type->isBuiltin()) {
                return;
            }

            $serviceId = ltrim($type->getName(), '\\');
            if ($container->hasDefinition($serviceId)) {
                $container->getDefinition($serviceId)->setPublic(true);

                return;
            }

            if ($container->hasAlias($serviceId)) {
                $container->getAlias($serviceId)->setPublic(true);
            }

            return;
        }

        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $innerType) {
                $this->publicizeParameterType($container, $innerType);
            }
        }
    }
}
