<?php

namespace HeimrichHannot\BackendLostPasswordBundle\Manager;

use Contao\Environment;
use HeimrichHannot\UtilsBundle\Container\ContainerUtil;
use Symfony\Component\Routing\RouterInterface;

class BackendLostPasswordManager {
    /**
     * @var \Twig_Environment
     */
    private $twig;
    /**
     * @var RouterInterface
     */
    private $router;
    /**
     * @var ContainerUtil
     */
    private $containerUtil;

    public function __construct(\Twig_Environment $twig, RouterInterface $router, ContainerUtil $containerUtil) {

        $this->twig = $twig;
        $this->router = $router;
        $this->containerUtil = $containerUtil;
    }

    public function getLostPasswordLink()
    {
        $requestRoute = $this->router->getRouteCollection()->get('contao_backend_request_password');

        if (version_compare(VERSION, '4.9', '<')) {
            $requestUrl = Environment::get('url') . ($this->containerUtil->isDev() ? '/app_dev.php' : '') . $requestRoute->getPath();
        } else {
            $requestUrl = Environment::get('url') . $requestRoute->getPath();
        }

        return $this->twig->render(
            '@ContaoBackendLostPassword/link_lost_password.html.twig', [
                'lostPasswordUrl' => $requestUrl
            ]
        );
    }
}
