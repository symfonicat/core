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
                $root.'/admin/templates' => 'symfonicat',
            ],
        ]);

        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'Symfonicat' => [
                        'is_bundle' => false,
                        'type' => 'attribute',
                        'dir' => $root.'/admin/src/Entity',
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
    }
}
