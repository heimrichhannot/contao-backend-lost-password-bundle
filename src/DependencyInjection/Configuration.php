<?php

namespace HeimrichHannot\BackendLostPasswordBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('huh_backend_lost_password');
        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode('add_to_template')
                    ->info('If true, that backend lost password link will be automatically added to the backed login template. Default false. Will be true in the next major version!')
                    ->defaultTrue()
                ->end()
            ->end();

        return $treeBuilder;
    }
}