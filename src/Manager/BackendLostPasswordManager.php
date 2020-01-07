<?php

namespace HeimrichHannot\BackendLostPasswordBundle\Manager;

use Contao\Environment;
use HeimrichHannot\UtilsBundle\Container\ContainerUtil;
use Symfony\Component\Routing\Router;

class BackendLostPasswordManager {
    /**
     * @var \Twig_Environment
     */
    private $twig;
    /**
     * @var Router
     */
    private $router;
    /**
     * @var ContainerUtil
     */
    private $containerUtil;

    public function __construct(\Twig_Environment $twig, Router $router, ContainerUtil $containerUtil) {

        $this->twig = $twig;
        $this->router = $router;
        $this->containerUtil = $containerUtil;
    }

    public function getLostPasswordLink()
    {
        $requestRoute = $this->router->getRouteCollection()->get('contao_backend_request_password');

        $requestUrl = Environment::get('url') . ($this->containerUtil->isDev() ? '/app_dev.php' : '') . $requestRoute->getPath();

        return $this->twig->render(
            '@ContaoBackendLostPassword/link_lost_password.html.twig', [
                'lostPasswordUrl' => $requestUrl
            ]
        );
    }
}