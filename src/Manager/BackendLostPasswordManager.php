<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\BackendLostPasswordBundle\Manager;

use Contao\Environment;
use HeimrichHannot\UtilsBundle\Util\Utils;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment as TwigEnvironment;

class BackendLostPasswordManager
{
    /** @var Utils */
    protected $utils;
    /**
     * @var TwigEnvironment
     */
    private $twig;
    /**
     * @var RouterInterface
     */
    private $router;

    public function __construct(TwigEnvironment $twig, RouterInterface $router, Utils $utils)
    {
        $this->twig = $twig;
        $this->router = $router;
        $this->utils = $utils;
    }

    /**
     * Return the link to the lost password page.
     *
     * Options:
     * - template: (string) Set a custom template. Default '@ContaoBackendLostPassword/link_lost_password.html.twig'
     *
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function getLostPasswordLink(array $options = []): string
    {
        $defaults = [
            'template' => '@ContaoBackendLostPassword/link_lost_password.html.twig',
        ];

        $options = array_merge($defaults, $options);

        $requestRoute = $this->router->getRouteCollection()->get('contao_backend_request_password');

        $requestUrl = Environment::get('url').$requestRoute->getPath();

        return $this->twig->render(
            $options['template'], [
                'lostPasswordUrl' => $requestUrl,
            ]
        );
    }
}
