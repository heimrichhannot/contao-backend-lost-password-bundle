<?php

/**
 * @package   Heimrich & Hannot Backend Lost Password Bundle
 * @copyright 2025, Heimrich & Hannot GmbH
 * @license   LGPL-3.0-or-later
 */

namespace HeimrichHannot\BackendLostPasswordBundle;

use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class HeimrichHannotBackendLostPasswordBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    protected function getContainerExtensionClass(): string
    {
        return DependencyInjection\HeimrichHannotBackendLostPasswordExtension::class;
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        return $this->extension ??= $this->createContainerExtension();
    }
}