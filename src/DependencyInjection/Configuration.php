<?php

namespace Wexample\SymfonyDev\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('wexample_symfony_dev');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('vendor_dev_paths')
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('js_dev_packages')
                    ->info('Glob patterns for local JS packages (e.g., /var/www/javascript-dev/wexample/*)')
                    ->scalarPrototype()->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
