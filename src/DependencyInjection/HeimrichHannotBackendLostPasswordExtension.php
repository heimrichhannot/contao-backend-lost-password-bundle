<?php

namespace HeimrichHannot\BackendLostPasswordBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class HeimrichHannotBackendLostPasswordExtension extends Extension
{
    public function getAlias(): string
    {
        return 'huh_backend_lost_password';
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        ###> Services ###
        $loader = new YamlFileLoader($container, new FileLocator(\dirname(__DIR__) . '/../config'));
        $loader->load('services.yaml');
        ###< Services ###

        ###> Configuration ###
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        $container->setParameter($this->getAlias(), $config);
        ###< Configuration ###
    }
}