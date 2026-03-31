<?php

namespace HeimrichHannot\BackendLostPasswordBundle\Controller;

use Contao\Backend;
use Contao\BackendTemplate;
use Contao\Config;
use Contao\CoreBundle\Controller\AbstractController;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Environment;
use Contao\Input;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use HeimrichHannot\UtilsBundle\Util\Utils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;

#[Route(
    path: '%contao.backend.route_prefix%/lost-password/request',
    name: self::NAME,
    defaults: [
        '_scope' => 'backend',
    ],
    methods: ['GET', 'POST']
)]
class RequestPasswordFormController extends AbstractController
{
    public const NAME = 'contao_backend_request_password';

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly RouterInterface $router,
        private readonly Utils $utils,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly UriSigner $uriSigner,
    ) {}

    public function __invoke(Request $request): Response
    {
        if (!$this->uriSigner->checkRequest($request)) {
            throw new AccessDeniedException();
        }

        $this->framework->initialize();

        $template = $this->createLegacyTemplate();

        return $this->handleRequest($template);
    }

    private function createLegacyTemplate(): BackendTemplate
    {
        $system = $this->framework->getAdapter(System::class);
        $system->loadLanguageFile('default');
        $system->loadLanguageFile('modules');
        $system->loadLanguageFile('tl_user');

        /** @var BackendTemplate|object $template */
        $template = new BackendTemplate('be_lost_password_request');

        $template->theme = Backend::getTheme();
        $template->messages = Message::generate();
        $template->base = Environment::get('base');
        $template->language = $GLOBALS['TL_LANGUAGE'] ?? 'en';
        $template->title = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['pw_new'] ?? '');
        $template->charset = Config::get('characterSet') ?? 'utf-8';
        $template->action = StringUtil::ampersand(Environment::get('request'));
        $template->headline = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['request'] ?? '';
        $template->explain = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['requestExplanationEmail'] ?? '';
        $template->submitButton = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['continue'] ?? 'continue');
        $template->username = $GLOBALS['TL_LANG']['tl_user']['email'][0] . '/' . $GLOBALS['TL_LANG']['tl_user']['username'][0];
        $template->requestToken = $this->csrfTokenManager->getDefaultTokenValue();
        $template->toLogin = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['toLogin'] ?? '';

        return $template;
    }

    private function handleRequest(BackendTemplate $template): Response
    {
        $username = Input::post('username');

        if ('tl_request_password' !== Input::post('FORM_SUBMIT') || !$username) {
            return $template->getResponse();
        }

        $template->headline = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['thankYou'] ?? '';
        $template->successMessage = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['requestLinkSentEmail'] ?? 'Success';
        $template->spamNote = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['spamNote'] ?? '';

        $userAdapter = $this->framework->getAdapter(UserModel::class);
        $user = $userAdapter->findOneBy(['LOWER(tl_user.email)=?'], [strtolower($username)]);
        $user ??= $userAdapter->findOneBy(['LOWER(tl_user.username)=?'], [strtolower($username)]);

        if (null === $user || !$user->email) {
            return $template->getResponse();
        }

        $token = 'PW' . substr(md5(uniqid(mt_rand(), true)), 2);
        if (!$resetRoute = $this->router->getRouteCollection()->get('contao_backend_reset_password')) {
            throw new \RuntimeException('The route "contao_backend_reset_password" is not defined.');
        }

        $resetUrl = Environment::get('url') . $resetRoute->getPath();
        $resetUrl = $this->utils->url()->addQueryStringParameterToUrl('token=' . $token, $resetUrl);

        $user->backendLostPasswordActivation = $token;
        $user->save();
//
//        if (class_exists(Notification::class) && $notificationId = $this->bundleConfig['nc_notification'] ?? 0) {
//            $this->sendResetNotification($notificationId, $user, $resetUrl);
//        } else {
//            $this->sendResetEmail($resetUrl, $user->email);
//        }

        $this->utils->container()->log("A new password has been requested for backend user ID {$user->id} ({$user->email})", __METHOD__, 'ACCESS');

        return $template->getResponse();
    }
}