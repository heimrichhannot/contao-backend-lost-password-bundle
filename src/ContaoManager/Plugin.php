<?php

namespace HeimrichHannot\BackendLostPasswordBundle\ContaoManager;

use Contao\ArrayUtil;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Config\ContainerBuilder;
use Contao\ManagerPlugin\Config\ExtensionPluginInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use HeimrichHannot\BackendLostPasswordBundle\HeimrichHannotBackendLostPasswordBundle;
use HeimrichHannot\BackendLostPasswordBundle\Routing\Matcher\ResetPasswordMatcher;
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
        if ('security' !== $extensionName) {
            return $extensionConfigs;
        }

        $extensionConfigs[0]['firewalls'] ??= [];
        $firewalls = &$extensionConfigs[0]['firewalls'];

        ArrayUtil::arrayInsert($firewalls, 0, [
            'request_passwort_form' => [
                'request_matcher' => ResetPasswordMatcher::class,
                'security' => false,
            ],
        ]);

        return $extensionConfigs;
    }
}
