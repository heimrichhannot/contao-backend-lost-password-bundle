<?php

namespace HeimrichHannot\BackendLostPasswordBundle\Manager;

use Contao\Environment;
use Symfony\Component\Routing\RouterInterface;

readonly class BackendLostPasswordManager
{
    public function __construct(
        private RouterInterface $router,
    ) {}

    /**
     * Return the link to the lost password page.
     */
    public function getRequestPasswordResetUrl(): string
    {
        if (!$requestRoute = $this->router->getRouteCollection()->get('contao_backend_request_password')) {
            throw new \RuntimeException('The route "contao_backend_request_password" is not defined.');
        }

        return Environment::get('url') . $requestRoute->getPath();
    }
}