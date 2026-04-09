<?php

namespace HeimrichHannot\BackendLostPasswordBundle\Controller;

use Contao\BackendTemplate;
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\OptIn\OptIn;
use Contao\CoreBundle\String\SimpleTokenParser;
use Contao\Input;
use Contao\Message;
use Contao\StringUtil;
use Contao\UserModel;
use HeimrichHannot\UtilsBundle\Util\Utils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Terminal42\NotificationCenterBundle\NotificationCenter;
use Twig\Markup;

#[Route(
    path: '%contao.backend.route_prefix%/lost-password/request',
    name: self::NAME,
    defaults: [
        '_scope' => 'backend',
    ],
    methods: ['GET', 'POST']
)]
class RequestPasswordChangeController extends AbstractLostPasswordController
{
    public const NAME = 'contao_backend_request_password_change';

    public const TOKEN_PREFIX = 'rpw';

    public function __construct(
        private readonly ContaoFramework        $framework,
        private readonly RouterInterface        $router,
        private readonly Utils                  $utils,
        private readonly MailerInterface        $mailer,
        private readonly ?NotificationCenter    $notificationCenter,
        private readonly RateLimiterFactory     $rateLimiterFactory,
        private readonly TranslatorInterface    $translator,
        private readonly OptIn                  $optIn,
        private readonly SimpleTokenParser      $tokenParser,
    ) {}

    public function __invoke(Request $request): Response
    {
        $this->framework->initialize();

        $template = $this->createLegacyTemplate('backend/lost_password/request');
        $template->headline = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['request'] ?? '';
        $template->explain = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['requestExplanationEmail'] ?? '';

        return $this->handleRequest($template, $request);
    }

    private function handleRequest(BackendTemplate $template, Request $request): Response
    {
        $username = Input::post('username');

        if ('tl_request_password' !== Input::post('FORM_SUBMIT') || !$username) {
            return $this->createTemplateResponse($template, $request);
        }

        $userAdapter = $this->framework->getAdapter(UserModel::class);
        $user = $userAdapter->findOneBy(['LOWER(tl_user.email)=?'], [strtolower($username)]);
        $user ??= $userAdapter->findOneBy(['LOWER(tl_user.username)=?'], [strtolower($username)]);

        if (null === $user || !$user->email) {
            return $this->createTemplateResponse($template, $request);
        }

        $limiter = $this->rateLimiterFactory->create($user->id);

        if (!$limiter->consume()->isAccepted()) {
            throw new \RuntimeException($this->translator->trans('MSC.tooManyPasswordResetAttempts', domain: 'contao_default'));
        }

        try {
            $this->sendResetEmail($request, $user);
        } catch (\Exception $e) {
            Message::addError($e->getMessage());
            return $this->createTemplateResponse($template, $request);
        }

        $template->setName('backend/lost_password/message_sent');
        $template->headline = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['thankYou'] ?? '';
        $template->successMessage = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['requestLinkSentEmail'] ?? 'Success';
        $template->spamNote = new Markup(
            $this->translator->trans('MSC.backendLostPassword.spamNote', domain: 'contao_default'),
            'UTF-8'
        );

        $this->utils->container()->log(
            "A new password has been requested for backend user ID {$user->id} ({$user->email})",
            __METHOD__,
            ContaoContext::ACCESS,
        );

        return $this->createTemplateResponse($template, $request);
    }

    private function sendResetEmail(Request $request, UserModel $user): void
    {
        $optInToken = $this->optIn->create(self::TOKEN_PREFIX, $user->email, ['tl_user' => [$user->id]]);

        $resetUrl = $this->router->generate(
            name: ChangePasswordController::NAME,
            parameters: ['token' => $optInToken->getIdentifier()],
            referenceType: RouterInterface::ABSOLUTE_URL,
        );

        if (true === $this->sendPerNotificationCenter($request, $user, $resetUrl)) {
            return;
        }

        $subject = $this->translator->trans(
            id: 'MSC.backendLostPassword.messageSubjectResetPassword',
            domain: 'contao_default'
        );
        $text = $this->translator->trans(
            id: 'MSC.backendLostPassword.messageBodyResetPassword',
            parameters: ['##reset_url##' => $resetUrl],
            domain: 'contao_default'
        );
        $text = $this->tokenParser->parse($text, ['reset_url' => $resetUrl]);

        if (!empty($GLOBALS['TL_ADMIN_EMAIL'])) {
            $from = new Address($GLOBALS['TL_ADMIN_EMAIL'], $GLOBALS['TL_ADMIN_NAME']);
        } elseif ($adminEmail = Config::get('adminEmail')) {
            $split = StringUtil::splitFriendlyEmail($adminEmail);
            $from = new Address($split[1], $split[0]);
        } else {
            throw new \Exception('No administrator e-mail address has been set.');
        }

        $email = (new Email())
            ->from($from)
            ->to($user->email)
            ->subject($subject)
            ->text($text);

        if ($transport = Config::get('beLostPassword_mailerTransport')) {
            $email->getHeaders()->addTextHeader('X-Transport', $transport);
        }

        $this->mailer->send($email);
    }

    private function sendPerNotificationCenter(Request $request, UserModel $user, string $resetUrl): bool
    {
        if (null === $this->notificationCenter) {
            return false;
        }

        $notification = (int)Config::get('beLostPassword_nc');
        if ($notification < 1) {
            return false;
        }

        $tokens = [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_username' => $user->username,
            'user_name' => $user->name,
        ];

        $tokens['recipient_email'] = $user->email;
        $tokens['domain'] = $request->getHttpHost();
        $tokens['link'] = $resetUrl;

        $this->notificationCenter->sendNotification($notification, $tokens);

        return true;
    }
}