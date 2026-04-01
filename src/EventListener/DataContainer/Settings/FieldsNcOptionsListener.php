<?php

namespace HeimrichHannot\BackendLostPasswordBundle\EventListener\DataContainer\Settings;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Terminal42\NotificationCenterBundle\NotificationCenter;

#[AsCallback('tl_settings', 'fields.beLostPassword_mailerTransport.options')]
class FieldsNcOptionsListener
{
    public function __construct(
        private readonly ?NotificationCenter $notificationCenter,
    ) {}

    public function __invoke(): array
    {
        if (null === $this->notificationCenter) {
            return [];
        }
        return $this->notificationCenter->getNotificationsForNotificationType('contao_core');
    }
}