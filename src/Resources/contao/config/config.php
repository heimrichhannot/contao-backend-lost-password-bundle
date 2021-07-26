<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

use HeimrichHannot\BackendLostPasswordBundle\EventListener\Contao\ParseTemplateListener;

if (\Contao\System::getContainer()->get('huh.utils.container')->isBackend()) {
    $GLOBALS['TL_CSS']['contao-backend-lost-password-bundle'] = 'bundles/contaobackendlostpassword/css/contao-backend-lost-password.css|static';
}

$GLOBALS['TL_HOOKS']['parseTemplate'][] = [ParseTemplateListener::class, '__invoke'];
