<?php

namespace Ecommerce\Bundle\ElasticsearchBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @author Philipp Wahala <philipp.wahala@gmail.com>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * Generates the configuration tree.
     *
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();

        $treeBuilder->root('ecommerce_elasticsearch')
            ->children()
                ->scalarNode('index')->defaultValue('ecommerce')->end()
                ->scalarNode('type')->defaultValue('product')->end()
            ->end();

        return $treeBuilder;
    }
}
