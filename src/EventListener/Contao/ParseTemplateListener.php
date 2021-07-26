<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\BackendLostPasswordBundle\EventListener\Contao;

use Contao\Template;
use HeimrichHannot\BackendLostPasswordBundle\Manager\BackendLostPasswordManager;

class ParseTemplateListener
{
    protected BackendLostPasswordManager $backendLostPasswordManager;
    protected array $bundleConfig;

    /**
     * ParseTemplateListener constructor.
     */
    public function __construct(BackendLostPasswordManager $backendLostPasswordManager, array $bundleConfig)
    {
        $this->backendLostPasswordManager = $backendLostPasswordManager;
        $this->bundleConfig = $bundleConfig;
    }

    public function __invoke(Template $template): void
    {
        if (true === $this->bundleConfig['add_to_template'] && 'be_login' === $template->getName()) {
            $template->messages = ($template->messages ?? '').$this->backendLostPasswordManager->getLostPasswordLink([
                'template' => '@ContaoBackendLostPassword/be_lost_password_link_main.html.twig',
            ]);
        }
    }
}
