<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\BackendLostPasswordBundle\Controller;

use Contao\Backend;
use Contao\BackendTemplate;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DC_Table;
use Contao\Email;
use Contao\Environment;
use Contao\Idna;
use Contao\Input;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use HeimrichHannot\UtilsBundle\Dca\DcaUtil;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;
use HeimrichHannot\UtilsBundle\Url\UrlUtil;
use HeimrichHannot\UtilsBundle\Util\Utils;
use NotificationCenter\Model\Notification;
use Patchwork\Utf8;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * @Route(defaults={"_scope" = "backend", "_token_check" = true})
 */
class BackendController
{
    /**
     * @var Utils
     */
    protected $utils;
    protected $requestStack;
    /**
     * @var array
     */
    protected $bundleConfig;
    /**
     * @var DcaUtil
     */
    private $dcaUtil;

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var ModelUtil
     */
    private $modelUtil;

    /**
     * @var UrlUtil
     */
    private $urlUtil;

    public function __construct(
        array $bundleConfig,
        DcaUtil $dcaUtil,
        ModelUtil $modelUtil,
        UrlUtil $urlUtil,
        ContaoFramework $framework,
        RouterInterface $router,
        Utils $utils,
        RequestStack $requestStack
    ) {
        $this->dcaUtil = $dcaUtil;
        $this->framework = $framework;
        $this->router = $router;
        $this->modelUtil = $modelUtil;
        $this->urlUtil = $urlUtil;
        $this->utils = $utils;
        $this->requestStack = $requestStack;
        $this->bundleConfig = $bundleConfig;
    }

    /**
     * Renders the "request password" form.
     *
     * @return Response
     *
     * @Route("/contao-be-lost-password/password/request", name="contao_backend_request_password")
     */
    public function requestPasswordAction()
    {
        $this->framework->initialize();

        $this->dcaUtil->loadLanguageFile('default');
        $this->dcaUtil->loadLanguageFile('modules');
        $this->dcaUtil->loadLanguageFile('tl_user');

        Controller::setStaticUrls();

        /** @var BackendTemplate|object $template */
        $template = new BackendTemplate('be_request_password');

        $template->theme = Backend::getTheme();
        $template->messages = Message::generate();
        $template->base = Environment::get('base');
        $template->language = $GLOBALS['TL_LANGUAGE'];
        $template->title = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['pw_new']);
        $template->charset = Config::get('characterSet');
        $template->action = ampersand(Environment::get('request'));
        $template->headline = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['request'];
        $template->explain = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['requestExplanationEmail'];
        $template->submitButton = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['continue']);
        $template->username = $GLOBALS['TL_LANG']['tl_user']['email'][0].'/'.$GLOBALS['TL_LANG']['tl_user']['username'][0];

        if ('tl_request_password' == Input::post('FORM_SUBMIT') && ($username = Input::post('username'))) {
            if ((null !== ($user = $this->modelUtil->findOneModelInstanceBy('tl_user', ['LOWER(tl_user.email)=?'], [strtolower($username)])) ||
                    null !== ($user = $this->modelUtil->findOneModelInstanceBy('tl_user', ['LOWER(tl_user.username)=?'], [strtolower($username)]))) && $user->email) {
                $token = 'PW'.substr(md5(uniqid(mt_rand(), true)), 2);
                $resetRoute = $this->router->getRouteCollection()->get('contao_backend_reset_password');

                if (version_compare(VERSION, '4.9', '<')) {
                    $resetUrl = Environment::get('url').($this->utils->container()->isDev() ? '/app_dev.php' : '').$resetRoute->getPath();
                } else {
                    $resetUrl = Environment::get('url').$resetRoute->getPath();
                }

                $resetUrl = $this->urlUtil->addQueryString('token='.$token, $resetUrl);

                $user->backendLostPasswordActivation = $token;
                $user->save();

                $notificationId = $this->bundleConfig['nc_notification'] ?? 0;

                if ($notificationId && class_exists('NotificationCenter\Model\Notification')) {
                    $notification = Notification::findByPk($notificationId);

                    if (null !== $notification) {
                        $tokens = [];

                        // Add user tokens
                        foreach ($user->row() as $k => $v) {
                            $tokens['user_'.$k] = $v;
                        }

                        $tokens['recipient_email'] = $user->email;
                        $tokens['domain'] = Idna::decode(Environment::get('host'));
                        $tokens['link'] = $resetUrl;

                        $notification->send($tokens, $GLOBALS['TL_LANGUAGE']);
                    } else {
                        throw new \Exception("Invalid configuration! A notification with id $notificationId could not be found.");
                    }
                } else {
                    $message = new Email();

                    $message->from = Config::get('adminEmail');
                    $message->fromName = Config::get('websiteTitle');
                    $message->subject = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['messageSubjectResetPassword'];
                    $message->text = str_replace('##reset_url##', $resetUrl, $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['messageBodyResetPassword']);

                    $message->sendTo($user->email);
                }

                Controller::log('A new password has been requested for backend user ID '.$user->id.' ('.$username->email.')', __METHOD__, TL_ACCESS);
            }

            $template->headline = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['thankYou'];
            $template->successMessage = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['requestLinkSentEmail'];
            $template->spamNote = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['spamNote'];

            return $template->getResponse();
        }

        return $template->getResponse();
    }

    /**
     * Renders the "reset password" form.
     *
     * @return Response
     *
     * @Route("/contao-be-lost-password/password/reset", name="contao_backend_reset_password")
     */
    public function resetPasswordAction()
    {
        $request = $this->requestStack->getCurrentRequest();

        $this->framework->initialize();

        $this->dcaUtil->loadLanguageFile('default');
        $this->dcaUtil->loadLanguageFile('modules');

        Controller::setStaticUrls();

        /** @var BackendTemplate|object $template */
        $template = new BackendTemplate('be_reset_password');

        $template->theme = Backend::getTheme();
        $template->messages = Message::generate();
        $template->base = Environment::get('base');
        $template->language = $GLOBALS['TL_LANGUAGE'];
        $template->title = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['pw_new']);
        $template->charset = Config::get('characterSet');
        $template->action = ampersand(Environment::get('request'));
        $template->headline = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['reset'];
        $template->explain = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['resetExplanation'];
        $template->submitButton = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['continue']);
        $template->password = $GLOBALS['TL_LANG']['MSC']['password'][0];
        $template->confirm = $GLOBALS['TL_LANG']['MSC']['confirm'][0];

        if (!($token = $request->query->get('token')) || 0 !== strncmp($token, 'PW', 2)) {
            $template->errorMessage = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['resetErrorExplanation'];

            return $template->getResponse();
        }

        if (null === ($user = $this->modelUtil->findOneModelInstanceBy('tl_user', ['tl_user.backendLostPasswordActivation=?'], [$token]))) {
            $template->errorMessage = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['resetErrorExplanation'];

            return $template->getResponse();
        }

        if ('tl_reset_password' == $request->request->get('FORM_SUBMIT')) {
            $password = $request->request->get('password');
            $confirm = $request->request->get('confirm');

            if ($password !== $confirm) {
                Message::addError($GLOBALS['TL_LANG']['ERR']['passwordMatch']);
            } elseif (Utf8::strlen($password) < Config::get('minPasswordLength')) {
                Message::addError(sprintf($GLOBALS['TL_LANG']['ERR']['passwordLength'], Config::get('minPasswordLength')));
            } elseif ($password == $user->username) {
                Message::addError($GLOBALS['TL_LANG']['ERR']['passwordName']);
            } else {
                $this->dcaUtil->loadDc('tl_user');

                if (\is_array($GLOBALS['TL_DCA']['tl_user']['fields']['password']['save_callback'])) {
                    $dc = new DC_Table('tl_user');
                    $dc->id = $user->id;

                    foreach ($GLOBALS['TL_DCA']['tl_user']['fields']['password']['save_callback'] as $callback) {
                        if (\is_array($callback)) {
                            $callbackObj = System::importStatic($callback[0]);
                            $password = $callbackObj->{$callback[1]}($password, $dc);
                        } elseif (\is_callable($callback)) {
                            $password = $callback($password, $dc);
                        }
                    }
                }

                $user->pwChange = '';
                $user->backendLostPasswordActivation = '';
                $user->password = password_hash($password, PASSWORD_DEFAULT);
                $user->save();

                Message::addConfirmation($GLOBALS['TL_LANG']['MSC']['pw_changed']);
                Controller::redirect('contao/main.php');
            }

            Controller::reload();
        }

        return $template->getResponse();
    }
}
