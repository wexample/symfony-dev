<?php

namespace Wexample\SymfonyDev\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('wexample_symfony_dev');

        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
            ->arrayNode('dev_vendors')
            ->info('Local development package vendors names')
            ->scalarPrototype()->end()
            ->defaultValue([])
            ->end()
            ->end();

        return $treeBuilder;
    }
}
