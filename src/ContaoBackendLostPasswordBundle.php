<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\BackendLostPasswordBundle;

use HeimrichHannot\BackendLostPasswordBundle\DependencyInjection\HeimrichHannotBackendLostPasswordExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class ContaoBackendLostPasswordBundle extends Bundle
{
    public function getContainerExtension()
    {
        return new HeimrichHannotBackendLostPasswordExtension();
    }
}
