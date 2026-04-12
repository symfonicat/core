<?php

namespace Symfonicat\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('symfonicat');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('asset_base_url')->defaultValue('')->end()
            ->end();

        return $treeBuilder;
    }
}
