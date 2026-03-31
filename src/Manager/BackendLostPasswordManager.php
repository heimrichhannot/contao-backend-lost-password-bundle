<?php

namespace HeimrichHannot\BackendLostPasswordBundle\Manager;

use Contao\Environment;
use HeimrichHannot\BackendLostPasswordBundle\Controller\RequestPasswordFormController;
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
        if (!$requestRoute = $this->router->getRouteCollection()->get(RequestPasswordFormController::NAME)) {
            throw new \RuntimeException('The route "'.RequestPasswordFormController::NAME.'" is not defined.');
        }

        return Environment::get('url') . $requestRoute->getPath();
    }
}