<?php

$dca = &$GLOBALS['TL_DCA']['tl_user'];

/**
 * Fields
 */
$fields = [
    'backendLostPasswordActivation' => [
        'eval' => ['doNotCopy' => true],
        'sql'  => "varchar(32) NOT NULL default ''"
    ],
];

$dca['fields'] = array_merge(is_array($dca['fields']) ? $dca['fields'] : [], $fields);