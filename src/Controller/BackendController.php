<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\BackendLostPasswordBundle\Controller;

use Contao\BackendTemplate;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DC_Table;
use Contao\Environment;
use Contao\Input;
use Contao\Message;
use Contao\System;
use HeimrichHannot\UtilsBundle\Container\ContainerUtil;
use HeimrichHannot\UtilsBundle\Dca\DcaUtil;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;
use HeimrichHannot\UtilsBundle\Url\UrlUtil;
use Patchwork\Utf8;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * @Route(defaults={"_scope" = "backend", "_token_check" = true})
 */
class BackendController
{
    /**
     * @var DcaUtil
     */
    private $dcaUtil;

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var ContainerUtil
     */
    private $containerUtil;

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

    /**
     * @var \Swift_Mailer
     */
    private $mailer;

    public function __construct(
        DcaUtil $dcaUtil,
        ContainerUtil $containerUtil,
        ModelUtil $modelUtil,
        UrlUtil $urlUtil,
        ContaoFramework $framework,
        RouterInterface $router,
        \Swift_Mailer $mailer
    ) {
        $this->dcaUtil       = $dcaUtil;
        $this->framework     = $framework;
        $this->containerUtil = $containerUtil;
        $this->router        = $router;
        $this->modelUtil     = $modelUtil;
        $this->urlUtil       = $urlUtil;
        $this->mailer        = $mailer;
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

        $template->theme        = \Backend::getTheme();
        $template->messages     = \Message::generate();
        $template->base         = \Environment::get('base');
        $template->language     = $GLOBALS['TL_LANGUAGE'];
        $template->title        = \StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['pw_new']);
        $template->charset      = \Config::get('characterSet');
        $template->action       = ampersand(\Environment::get('request'));
        $template->headline     = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['request'];
        $template->explain      = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['requestExplanationEmail'];
        $template->submitButton = \StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['continue']);
        $template->username     = $GLOBALS['TL_LANG']['tl_user']['email'][0];

        if (Input::post('FORM_SUBMIT') == 'tl_request_password' && ($username = Input::post('username'))) {
            if (null !== ($user = $this->modelUtil->findOneModelInstanceBy('tl_user', ['LOWER(tl_user.email)=?'], [strtolower($username)])) && $user->email) {
                $token      = 'PW' . substr(md5(uniqid(mt_rand(), true)), 2);
                $resetRoute = $this->router->getRouteCollection()->get('contao_backend_reset_password');

                $resetUrl = Environment::get('url') . ($this->containerUtil->isDev() ? '/app_dev.php' : '') . $resetRoute->getPath();
                $resetUrl = $this->urlUtil->addQueryString('token=' . $token, $resetUrl);

                $user->backendLostPasswordActivation = $token;
                $user->save();

                /** @var \Swift_Mime_SimpleMessage $message */
                $message = $this->mailer->createMessage();

                $message->setFrom([Config::get('adminEmail') => Config::get('websiteTitle') ?: Config::get('adminEmail')]);
                $message->setTo([$user->email => $user->name ?: $user->email]);
                $message->setSubject($GLOBALS['TL_LANG']['MSC']['backendLostPassword']['messageSubjectResetPassword']);
                $message->setBody(
                    str_replace('##reset_url##', $resetUrl, $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['messageBodyResetPassword'])
                );

                $this->mailer->send($message);
            }

            $template->headline       = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['thankYou'];
            $template->successMessage = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['requestLinkSentEmail'];
            $template->spamNote       = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['spamNote'];

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
        $request = $this->containerUtil->getCurrentRequest();

        $this->framework->initialize();

        $this->dcaUtil->loadLanguageFile('default');
        $this->dcaUtil->loadLanguageFile('modules');

        Controller::setStaticUrls();

        /** @var BackendTemplate|object $template */
        $template = new BackendTemplate('be_reset_password');

        $template->theme        = \Backend::getTheme();
        $template->messages     = \Message::generate();
        $template->base         = \Environment::get('base');
        $template->language     = $GLOBALS['TL_LANGUAGE'];
        $template->title        = \StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['pw_new']);
        $template->charset      = \Config::get('characterSet');
        $template->action       = ampersand(\Environment::get('request'));
        $template->headline     = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['reset'];
        $template->explain      = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['resetExplanation'];
        $template->submitButton = \StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['continue']);
        $template->password      = $GLOBALS['TL_LANG']['MSC']['password'][0];
        $template->confirm      = $GLOBALS['TL_LANG']['MSC']['confirm'][0];

        if (!($token = $request->query->get('token')) || strncmp($token, 'PW', 2) !== 0) {
            $template->errorMessage = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['resetErrorExplanation'];

            return $template->getResponse();
        }

        if (null === ($user = $this->modelUtil->findOneModelInstanceBy('tl_user', ['tl_user.backendLostPasswordActivation=?'], [$token]))) {
            $template->errorMessage = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['resetErrorExplanation'];

            return $template->getResponse();
        }

        if ($request->request->get('FORM_SUBMIT') == 'tl_reset_password') {
            $password = $request->request->get('password');
            $confirm = $request->request->get('confirm');

            if ($password !== $confirm)
            {
                Message::addError($GLOBALS['TL_LANG']['ERR']['passwordMatch']);
            }
            elseif (Utf8::strlen($password) < Config::get('minPasswordLength'))
            {
                Message::addError(sprintf($GLOBALS['TL_LANG']['ERR']['passwordLength'], Config::get('minPasswordLength')));
            }
            elseif ($password == $user->username)
            {
                Message::addError($GLOBALS['TL_LANG']['ERR']['passwordName']);
            }
            else
            {
                $this->dcaUtil->loadDc('tl_user');

                if (\is_array($GLOBALS['TL_DCA']['tl_user']['fields']['password']['save_callback']))
                {
                    $dc = new DC_Table('tl_user');
                    $dc->id = $user->id;

                    foreach ($GLOBALS['TL_DCA']['tl_user']['fields']['password']['save_callback'] as $callback)
                    {
                        if (\is_array($callback))
                        {
                            $callbackObj = System::importStatic($callback[0]);
                            $password = $callbackObj->{$callback[1]}($password, $dc);
                        }
                        elseif (\is_callable($callback))
                        {
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
