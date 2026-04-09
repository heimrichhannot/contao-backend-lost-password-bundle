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
                    ->info('If true, the backend lost password link will be added automatically to the backend login template. Set to false if you want to render the link yourself.')
                    ->defaultTrue()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
