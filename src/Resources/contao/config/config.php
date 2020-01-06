<?php

if (\Contao\System::getContainer()->get('huh.utils.container')->isBackend()) {
    $GLOBALS['TL_CSS']['contao-multi-column-editor-bundle'] = 'bundles/contaobackendlostpassword/css/contao-backend-lost-password.css|static';
}