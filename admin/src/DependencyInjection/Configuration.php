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
                ->arrayNode('vendors')
                    ->scalarPrototype()->end()
                    ->defaultValue(['symfonicat'])
                ->end()
                ->arrayNode('admin')
                    ->ignoreExtraKeys(false)
                    ->variablePrototype()->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
