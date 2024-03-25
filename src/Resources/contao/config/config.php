<?php

/*
 * Copyright (c) 2024 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

use Contao\System;
use HeimrichHannot\BackendLostPasswordBundle\EventListener\Contao\ParseTemplateListener;
use HeimrichHannot\UtilsBundle\Util\Utils;

/*
 * Assets
 */
if (System::getContainer()->get(Utils::class)->container()->isBackend()) {
    $GLOBALS['TL_CSS']['contao-backend-lost-password-bundle'] = 'bundles/contaobackendlostpassword/css/contao-backend-lost-password.css|static';
}

/*
 * Hooks
 */
$GLOBALS['TL_HOOKS']['parseTemplate'][] = [ParseTemplateListener::class, '__invoke'];

/*
 * Notification Center Notification Types
 */
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE'] = array_merge_recursive(
    (array) ($GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE'] ?? []),
    [
        'contao' => [
            'user_password' => [
                'recipients' => ['recipient_email'],
                'email_subject' => ['domain', 'link', 'user_*', 'recipient_email'],
                'email_text' => ['domain', 'link', 'user_*', 'recipient_email'],
                'email_html' => ['domain', 'link', 'user_*', 'recipient_email'],
                'file_name' => ['domain', 'link', 'user_*', 'recipient_email'],
                'file_content' => ['domain', 'link', 'user_*', 'recipient_email'],
                'email_sender_name' => ['recipient_email'],
                'email_sender_address' => ['recipient_email'],
                'email_recipient_cc' => ['recipient_email'],
                'email_recipient_bcc' => ['recipient_email'],
                'email_replyTo' => ['recipient_email'],
            ],
        ],
    ]
);
