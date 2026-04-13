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

        $loader = new YamlFileLoader($container, new FileLocator(dirname(__DIR__, 3).'/config'));
        $loader->load('services.yaml');
    }
}
