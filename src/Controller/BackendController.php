<?php

/**
 * @package   Contao Backend Lost Password Bundle
 * @copyright Heimrich & Hannot GmbH, 2024
 * @license   LGPL-3.0-or-later
 */

namespace HeimrichHannot\BackendLostPasswordBundle\Controller;

use Contao\Backend;
use Contao\BackendTemplate;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DC_Table;
use Contao\Email;
use Contao\Environment;
use Contao\Idna;
use Contao\Input;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Exception;
use HeimrichHannot\UtilsBundle\Util\Utils;
use NotificationCenter\Model\Notification;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * @Route(defaults={"_scope" = "backend", "_token_check" = true})
 */
class BackendController
{
    protected array $bundleConfig;
    protected ContaoCsrfTokenManager $csrfTokenManager;
    protected ContaoFramework $framework;
    protected RequestStack $requestStack;
    protected RouterInterface $router;
    protected Utils $utils;

    public function __construct(
        array $bundleConfig,
        ContaoCsrfTokenManager $csrfTokenManager,
        ContaoFramework $framework,
        RequestStack $requestStack,
        RouterInterface $router,
        Utils $utils
    ) {
        $this->bundleConfig = $bundleConfig;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->router = $router;
        $this->utils = $utils;
    }

    /**
     * @deprecated Remove when you can. Ported from Contao 4.13 {@link \Contao\Controller::setStaticUrls()}
     */
    private static function setStaticUrls(): void
    {
        if (defined('TL_FILES_URL'))
        {
            return;
        }

        define('TL_ASSETS_URL', System::getContainer()->get('contao.assets.assets_context')->getStaticUrl());
        define('TL_FILES_URL', System::getContainer()->get('contao.assets.files_context')->getStaticUrl());

        // Deprecated since Contao 4.0, to be removed in Contao 5.0
        define('TL_SCRIPT_URL', TL_ASSETS_URL);
        define('TL_PLUGINS_URL', TL_ASSETS_URL);
    }

    /**
     * Renders the "request password" form.
     *
     * @throws Exception
     * @Route("/contao-be-lost-password/password/request", name="contao_backend_request_password")
     */
    public function requestPasswordAction(): Response
    {
        $this->framework->initialize();

        $system = $this->framework->getAdapter(System::class);
        $system->loadLanguageFile('default');
        $system->loadLanguageFile('modules');
        $system->loadLanguageFile('tl_user');

        static::setStaticUrls();

        /** @var BackendTemplate|object $template */
        $template = new BackendTemplate('be_request_password');

        $template->theme = Backend::getTheme();
        $template->messages = Message::generate();
        $template->base = Environment::get('base');
        $template->language = $GLOBALS['TL_LANGUAGE'];
        $template->title = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['pw_new']);
        $template->charset = Config::get('characterSet');
        $template->action = StringUtil::ampersand(Environment::get('request'));
        $template->headline = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['request'];
        $template->explain = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['requestExplanationEmail'];
        $template->submitButton = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['continue']);
        $template->username = $GLOBALS['TL_LANG']['tl_user']['email'][0].'/'.$GLOBALS['TL_LANG']['tl_user']['username'][0];
        $template->requestToken = $this->csrfTokenManager->getDefaultTokenValue();

        $username = Input::post('username');

        if (Input::post('FORM_SUBMIT') !== 'tl_request_password' || !$username)
        {
            return $template->getResponse();
        }

        $template->headline = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['thankYou'];
        $template->successMessage = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['requestLinkSentEmail'];
        $template->spamNote = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['spamNote'];

        $user = $this->utils->model()->findOneModelInstanceBy('tl_user', ['LOWER(tl_user.email)=?'], [strtolower($username)]);
        $user ??= $this->utils->model()->findOneModelInstanceBy('tl_user', ['LOWER(tl_user.username)=?'], [strtolower($username)]);

        if ($user === null || !$user->email)
        {
            return $template->getResponse();
        }

        $token = 'PW'.substr(md5(uniqid(mt_rand(), true)), 2);
        $resetRoute = $this->router->getRouteCollection()->get('contao_backend_reset_password');

        $resetUrl = Environment::get('url').$resetRoute->getPath();
        $resetUrl = $this->utils->url()->addQueryStringParameterToUrl('token='.$token, $resetUrl);

        $user->backendLostPasswordActivation = $token;
        $user->save();

        if (class_exists(Notification::class) && $notificationId = $this->bundleConfig['nc_notification'] ?? 0)
        {
            $this->sendResetNotification($notificationId, $user, $resetUrl);
        }
        else
        {
            $this->sendResetEmail($resetUrl, $user->email);
        }

        $this->utils->container()->log("A new password has been requested for backend user ID {$user->id} ({$user->email})", __METHOD__, 'ACCESS');

        return $template->getResponse();
    }

    /**
     * @throws Exception
     */
    protected function sendResetEmail(string $resetUrl, string $to): void
    {
        $email = new Email();

        $email->from = Config::get('adminEmail');
        $email->fromName = Config::get('websiteTitle');
        $email->subject = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['messageSubjectResetPassword'] ?? 'Reset Password';
        $email->text = str_replace('##reset_url##', $resetUrl, $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['messageBodyResetPassword'] ?? 'Reset: ##reset_url##');

        if ($transport = Config::get('beLostPassword_mailerTransport'))
        {
            $email->addHeader('X-Transport', $transport);
        }

        $email->sendTo($to);
    }

    /**
     * @throws Exception
     */
    protected function sendResetNotification($notificationId, $user, $resetUrl): void
    {
        $notification = Notification::findByPk($notificationId);

        if ($notification === null) {
            throw new Exception("Invalid configuration! A notification with id $notificationId could not be found.");
        }

        $secretFields = [
            'backendTheme',
            'fullscreen',
            'uploader',
            'showHelp',
            'thumbnails',
            'useRTE',
            'useCE',
            'password',
            'pwChange',
            'groups',
            'inherit',
            'modules',
            'themes',
            'elements',
            'fields',
            'pagemounts',
            'alpty',
            'fop',
            'imageSizes',
            'forms',
            'formp',
            'amg',
            'session',
            'secret',
            'trustedTokenVersion',
            'backupCodes',
            'modalp',
            'modals',
            'submissionsp',
            'submissionss',
            'categories',
            'readerbundlep',
            'readerbundles',
            'faqs',
            'faqp',
            'news',
            'newp',
            'newsfeeds',
            'newsfeedp',
            'calendars',
            'calendarp',
            'calendarfeeds',
            'calendarfeedp',
            'newsletters',
            'newsletterp',
        ];

        $tokens = [];

        // Add user tokens
        foreach ($user->row() as $k => $v)
        {
            // skip configuration and secret fields
            if (\in_array($k, $secretFields)) {
                continue;
            }

            // skip fields leading to issues on json_encode
            if (false !== \json_encode($v)) {
                $tokens['user_'.$k] = $v;
            }
        }

        $tokens['recipient_email'] = $user->email;
        $tokens['domain'] = Idna::decode(Environment::get('host'));
        $tokens['link'] = $resetUrl;

        $notification->send($tokens, $GLOBALS['TL_LANGUAGE']);
    }

    /**
     * Renders the "reset password" form.
     *
     * @Route("/contao-be-lost-password/password/reset", name="contao_backend_reset_password")
     */
    public function resetPasswordAction(Request $request): Response
    {
        // $request = $this->requestStack->getCurrentRequest();

        $this->framework->initialize();

        $system = $this->framework->getAdapter(System::class);
        $system->loadLanguageFile('default');
        $system->loadLanguageFile('modules');

        static::setStaticUrls();

        /** @var BackendTemplate|object $template */
        $template = new BackendTemplate('be_reset_password');

        $template->theme = Backend::getTheme();
        $template->messages = Message::generate();
        $template->base = Environment::get('base');
        $template->language = $GLOBALS['TL_LANGUAGE'];
        $template->title = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['pw_new'] ?? '');
        $template->charset = Config::get('characterSet');
        $template->action = StringUtil::ampersand(Environment::get('request'));
        $template->headline = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['reset'] ?? null;
        $template->explain = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['resetExplanation'] ?? null;
        $template->submitButton = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['continue'] ?? '');
        $template->password = $GLOBALS['TL_LANG']['MSC']['password'][0] ?? null;
        $template->confirm = $GLOBALS['TL_LANG']['MSC']['confirm'][0] ?? null;
        $template->requestToken = $this->csrfTokenManager->getDefaultTokenValue();

        if (!($token = $request->query->get('token')) || 0 !== strncmp($token, 'PW', 2)) {
            $template->errorMessage = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['resetErrorExplanation'];
            return $template->getResponse();
        }

        $user = $this->utils->model()->findOneModelInstanceBy('tl_user', ['tl_user.backendLostPasswordActivation=?'], [$token]);

        if (null === $user) {
            $template->errorMessage = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['resetErrorExplanation'];
            return $template->getResponse();
        }

        if ('tl_reset_password' !== $request->request->get('FORM_SUBMIT')) {
            return $template->getResponse();
        }

        $password = $request->request->get('password');
        $confirm = $request->request->get('confirm');

        if ($password !== $confirm)
        {
            Message::addError($GLOBALS['TL_LANG']['ERR']['passwordMatch'] ?? 'Passwords don\'t match.');
        }
        elseif (mb_strlen($password) < Config::get('minPasswordLength'))
        {
            Message::addError(sprintf(
                $GLOBALS['TL_LANG']['ERR']['passwordLength'] ?? 'Minimum required password length is %s.',
                Config::get('minPasswordLength')
            ));
        }
        elseif (str_contains($password, $user->username))
        {
            Message::addError($GLOBALS['TL_LANG']['ERR']['passwordName'] ?? 'The password must not contain the username.');
        }
        else
        {
            $table = 'tl_user';
            if (!isset($GLOBALS['TL_DCA'][$table])) {
                /** @var Controller $controller */
                $controller = $this->framework->getAdapter(Controller::class);
                $controller->loadDataContainer($table);
            }

            if (\is_array($GLOBALS['TL_DCA']['tl_user']['fields']['password']['save_callback'] ?? null)) {
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

            $user->pwChange = false;
            $user->backendLostPasswordActivation = '';
            $user->password = password_hash($password, \PASSWORD_DEFAULT);
            $user->save();

            Message::addConfirmation($GLOBALS['TL_LANG']['MSC']['pw_changed']
                ?? 'The password has been changed successfully.');
            Controller::redirect('contao');
        }

        Controller::reload();

        return $template->getResponse();
    }
}
