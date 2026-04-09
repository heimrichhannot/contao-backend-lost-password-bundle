<?php

namespace HeimrichHannot\BackendLostPasswordBundle\EventListener\DataContainer\Settings;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Mailer\AvailableTransports;

#[AsCallback('tl_settings', 'fields.beLostPassword_mailerTransport.options')]
class FieldsMailerTransportOptionsListener
{
    public function __construct(
        private readonly AvailableTransports $transports
    ) {
    }

    public function __invoke(): array
    {
        return $this->transports->getTransportOptions();
    }
}
