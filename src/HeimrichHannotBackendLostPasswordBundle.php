<?php

/**
 * @copyright 2025, Heimrich & Hannot GmbH
 * @license   LGPL-3.0-or-later
 */

namespace HeimrichHannot\BackendLostPasswordBundle;

use HeimrichHannot\BackendLostPasswordBundle\DependencyInjection\HeimrichHannotBackendLostPasswordExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class HeimrichHannotBackendLostPasswordBundle extends Bundle
{
    #[\Override]
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    #[\Override]
    protected function getContainerExtensionClass(): string
    {
        return HeimrichHannotBackendLostPasswordExtension::class;
    }

    #[\Override]
    public function getContainerExtension(): ?ExtensionInterface
    {
        return $this->extension ??= $this->createContainerExtension();
    }
}
