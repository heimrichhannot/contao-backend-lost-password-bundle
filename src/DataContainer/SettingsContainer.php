<?php

namespace HeimrichHannot\BackendLostPasswordBundle\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Mailer\AvailableTransports;

class SettingsContainer
{
    protected AvailableTransports $transports;

    public function __construct(AvailableTransports $transports)
    {
        $this->transports = $transports;
    }

    #[AsCallback('tl_settings', 'fields.beLostPassword_mailerTransport.options')]
    public function getMailerTransportOptions(): array
    {
        return $this->transports->getTransportOptions();
    }
}