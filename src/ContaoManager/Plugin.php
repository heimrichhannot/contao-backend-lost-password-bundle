<?php

namespace HeimrichHannot\BackendLostPasswordBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Config\ContainerBuilder;
use Contao\ManagerPlugin\Config\ExtensionPluginInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use HeimrichHannot\BackendLostPasswordBundle\HeimrichHannotBackendLostPasswordBundle;
use HeimrichHannot\UtilsBundle\StaticUtil\SUtils;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class Plugin implements BundlePluginInterface, RoutingPluginInterface, ExtensionPluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(HeimrichHannotBackendLostPasswordBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class])
        ];
    }

    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel)
    {
        $routes = '@HeimrichHannotBackendLostPasswordBundle/config/routes.yaml';

        return $resolver->resolve($routes)->load($routes);
    }

    public function getExtensionConfig($extensionName, array $extensionConfigs, ContainerBuilder $container): array
    {
        // don't check for backend login
        if ('security' === $extensionName) {
            SUtils::array()->insertBeforeKey($extensionConfigs[0]['firewalls'], 'install', 'lost-password', [
                'pattern' => '^/contao-be-lost-password',
                'security' => false
            ]);
        }

        return $extensionConfigs;
    }
}