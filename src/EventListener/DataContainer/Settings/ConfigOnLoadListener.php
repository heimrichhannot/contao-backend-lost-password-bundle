<?php

namespace HeimrichHannot\BackendLostPasswordBundle\EventListener\DataContainer\Settings;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Terminal42\NotificationCenterBundle\NotificationCenter;

class ConfigOnLoadListener
{
    public function __construct(
        private readonly ?NotificationCenter $notificationCenter,
    ) {
    }

    #[AsCallback(table: 'tl_settings', target: 'config.onload')]
    public function __invoke(?DataContainer $dc = null): void
    {
        if (null === $this->notificationCenter) {
            $GLOBALS['TL_DCA']['tl_settings']['fields']['beLostPassword_nc']['eval']['disabled'] = true;
        }
    }
}
