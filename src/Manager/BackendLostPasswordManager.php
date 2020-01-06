<?php

namespace HeimrichHannot\BackendLostPasswordBundle\Manager;

class BackendLostPasswordManager {
    /**
     * @var \Twig_Environment
     */
    private $twig;

    public function __construct(\Twig_Environment $twig) {

        $this->twig = $twig;
    }

    public function getLostPasswordLink()
    {
        return $this->twig->render(
            '@ContaoBackendLostPassword/link_lost_password.html.twig'
        );
    }
}