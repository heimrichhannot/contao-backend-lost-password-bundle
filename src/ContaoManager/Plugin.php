<?php

namespace HeimrichHannot\BackendLostPasswordBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Config\ConfigPluginInterface;
use Contao\ManagerPlugin\Config\ExtensionPluginInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use HeimrichHannot\BackendLostPasswordBundle\ContaoBackendLostPasswordBundle;
use HeimrichHannot\UtilsBundle\Arrays\ArrayUtil;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Contao\ManagerPlugin\Config\ContainerBuilder;

class Plugin implements BundlePluginInterface, ConfigPluginInterface, RoutingPluginInterface, ExtensionPluginInterface
{
    /**
     * {@inheritdoc}
     */
    public function getBundles(ParserInterface $parser)
    {
        return [
            BundleConfig::create(ContaoBackendLostPasswordBundle::class)->setLoadAfter([ContaoCoreBundle::class]),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader, array $managerConfig)
    {
        $loader->load('@ContaoBackendLostPasswordBundle/Resources/config/services.yml');
    }

    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel)
    {
        $file = __DIR__.'/../Resources/config/routing.yml';

        return $resolver->resolve($file)->load($file);
    }

    public function getExtensionConfig($extensionName, array $extensionConfigs, ContainerBuilder $container)
    {
        // don't check for backend login
        if ('security' === $extensionName) {
            ArrayUtil::insertBeforeKey($extensionConfigs[0]['firewalls'], 'install', 'lost-password', [
                'pattern' => '^/contao-be-lost-password',
                'security' => false
            ]);
        }

        return $extensionConfigs;
    }
}