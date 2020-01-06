<?php

if (\Contao\System::getContainer()->get('huh.utils.container')->isBackend()) {
    $GLOBALS['TL_CSS']['contao-backend-lost-password-bundle'] = 'bundles/contaobackendlostpassword/css/contao-backend-lost-password.css|static';
}