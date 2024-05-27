<?php

/**
 * @package   Contao Backend Lost Password Bundle
 * @copyright Heimrich & Hannot GmbH, 2024
 * @license   LGPL-3.0-or-later
 */

namespace HeimrichHannot\BackendLostPasswordBundle\EventListener\Contao;

use Contao\Template;
use HeimrichHannot\BackendLostPasswordBundle\Manager\BackendLostPasswordManager;

class ParseTemplateListener
{
    /** @var BackendLostPasswordManager */
    protected $backendLostPasswordManager;
    /** @var array */
    protected $bundleConfig;

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
        if (true !== $this->bundleConfig['add_to_template']) {
            return;
        }

        if ('be_login' !== $template->getName()) {
            return;
        }

        $messages = $this->backendLostPasswordManager->getLostPasswordLink([
            'template' => '@ContaoBackendLostPassword/be_lost_password_link_main.html.twig'
        ]);

        $messages .= ($template->messages ?? '');

        $template->messages = $messages;
    }
}