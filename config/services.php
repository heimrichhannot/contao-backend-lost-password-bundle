<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Terminal42\NotificationCenterBundle\NotificationCenter;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->bind('$bundleConfig', '%huh_backend_lost_password%')
        ->bind(NotificationCenter::class, service(NotificationCenter::class)->nullOnInvalid())
        ->bind(RateLimiterFactory::class, service('huh.blp.rate_limit.factory'))
    ;

    $services
        ->load('HeimrichHannot\\BackendLostPasswordBundle\\', '../src/')
        ->exclude([
            '../src/ContaoManager',
            '../src/DependencyInjection',
        ]);

    $services
        ->set('huh.blp.rate_limit.factory', RateLimiterFactory::class)
        ->args([
            [
                'id' => 'huh.blp',
                'policy' => 'fixed_window',
                'limit' => 3,
                'interval' => '15 minutes'
            ],
            service('huh.blp.user_password_storage'),
            service('lock_factory')->nullOnInvalid(),
        ]);

    $services
        ->set('huh.blp.user_password_storage', CacheStorage::class)
        ->args([
            service('cache.rate_limiter'),
        ]);
};
