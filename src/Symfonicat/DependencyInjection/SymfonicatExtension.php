<?php

namespace Symfonicat\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

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
                        'dir' => $root.'/src/Symfonicat/Entity',
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
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('symfonicat.asset_base_url', $config['asset_base_url']);
        $vendors = array_values(array_unique(array_filter(array_map(
            static fn (mixed $vendor): string => trim((string) $vendor),
            $config['vendors'],
        ))));
        $container->setParameter('symfonicat.vendors', $vendors);

        $root = dirname(__DIR__, 3);
        $loader = new YamlFileLoader($container, new FileLocator($root.'/config'));
        $loader->load('services.yaml');

        foreach ($vendors as $vendor) {
            foreach (glob($root.'/vendor/'.trim($vendor, '/').'/*/config/services.yaml') ?: [] as $serviceConfig) {
                $packageLoader = new YamlFileLoader($container, new FileLocator(dirname($serviceConfig)));
                $packageLoader->load(basename($serviceConfig));
            }
        }
    }
}
