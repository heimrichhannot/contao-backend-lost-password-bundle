<?php

namespace HeimrichHannot\BackendLostPasswordBundle\DataContainer;

use Contao\CoreBundle\Mailer\AvailableTransports;
use Contao\CoreBundle\ServiceAnnotation\Callback;

class SettingsContainer
{
    protected AvailableTransports $transports;

    public function __construct(AvailableTransports $transports)
    {
        $this->transports = $transports;
    }

    /**
     * @Callback(table="tl_settings", target="fields.beLostPassword_mailerTransport.options")
     */
    public function getMailerTransportOptions(): array
    {
        return $this->transports->getTransportOptions();
    }
}