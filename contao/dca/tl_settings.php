<?php

use Contao\CoreBundle\DataContainer\PaletteManipulator;

$dca = &$GLOBALS['TL_DCA']['tl_settings'];

PaletteManipulator::create()
    ->addLegend('beLostPassword_legend', 'backend_legend', PaletteManipulator::POSITION_AFTER, true)
    ->addField(['beLostPassword_mailerTransport', 'beLostPassword_nc'], 'beLostPassword_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_settings');

$dca['fields']['beLostPassword_nc'] = [
    'inputType' => 'select',
    'eval' => [
        'tl_class' => 'w50',
        'includeBlankOption' => true
    ],
];
$dca['fields']['beLostPassword_mailerTransport'] = [
    'inputType' => 'select',
    'eval' => [
        'tl_class' => 'w50',
        'includeBlankOption' => true
    ],
];
