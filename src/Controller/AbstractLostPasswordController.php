<?php

namespace HeimrichHannot\BackendLostPasswordBundle\Controller;

use Composer\InstalledVersions;
use Contao\Backend;
use Contao\BackendTemplate;
use Contao\Config;
use Contao\CoreBundle\Controller\AbstractController;
use Contao\CoreBundle\String\HtmlAttributes;
use Contao\Environment;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractLostPasswordController extends AbstractController
{
    protected function createLegacyTemplate(string $templateName): BackendTemplate
    {
        $system = $this->getContaoAdapter(System::class);
        $system->loadLanguageFile('default');
        $system->loadLanguageFile('modules');
        $system->loadLanguageFile('tl_user');

        $template = new BackendTemplate($templateName);

        $template->theme = Backend::getTheme();
        $template->base = Environment::get('base');
        $template->language = $GLOBALS['TL_LANGUAGE'] ?? 'en';
        $template->title = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['pw_new'] ?? '');
        $template->charset = Config::get('characterSet') ?? 'utf-8';
        $template->action = StringUtil::ampersand(Environment::get('request'));

        $version = InstalledVersions::getPrettyVersion('contao/core-bundle') ?? '';

        if (preg_match('/^v?(\d+)\.(\d+)/', $version, $matches)) {
            $class = 'contao-'.$matches[1].'-'.$matches[2];
        } else {
            $class = 'contao-unknown';
        }

        $template->attributes = (new HtmlAttributes())->addClass($class);

        $template->username = $GLOBALS['TL_LANG']['tl_user']['email'][0] . '/' . $GLOBALS['TL_LANG']['tl_user']['username'][0];
        $template->requestToken = $this->container->get('contao.csrf.token_manager')->getDefaultTokenValue();
        $template->toLogin = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['toLogin'] ?? '';
        $template->host = Backend::getDecodedHostname();
        $template->jsDisabled = $GLOBALS['TL_LANG']['MSC']['jsDisabled'];
        $template->submitButton = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['continue'] ?? '');

        return $template;
    }

    protected function createTemplateResponse(BackendTemplate $template): Response
    {
        if (Message::hasMessages()) {
            $template->messages = Message::generate();
        } else {
            $template->messages = '';
        }
        return $template->getResponse();
    }
}