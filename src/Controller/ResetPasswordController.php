<?php

/**
 * @copyright Heimrich & Hannot GmbH, 2024
 * @license   LGPL-3.0-or-later
 */

namespace HeimrichHannot\BackendLostPasswordBundle\Controller;

use Contao\Backend;
use Contao\BackendTemplate;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Controller\AbstractController;
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
use Contao\UserModel;
use HeimrichHannot\UtilsBundle\Util\Utils;
use NotificationCenter\Model\Notification;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

#[Route(
    path: '%contao.backend.route_prefix%/lost-password',
    defaults: [
        '_scope' => 'backend',
    ]
)]
class ResetPasswordController extends AbstractController
{
    public function __construct(
        private readonly array                  $bundleConfig,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly ContaoFramework        $framework,
        private readonly RouterInterface        $router,
        private readonly Utils                  $utils
    ) {}




    /**
     * @throws \Exception
     */
    protected function sendResetNotification($notificationId, $user, $resetUrl): void
    {
        /**
         * @phpstan-ignore class.notFound
         */
        $notification = Notification::findByPk($notificationId);

        if (null === $notification) {
            throw new \Exception("Invalid configuration! A notification with id $notificationId could not be found.");
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
        foreach ($user->row() as $k => $v) {
            // skip configuration and secret fields
            if (\in_array($k, $secretFields)) {
                continue;
            }

            // skip fields leading to issues on json_encode
            if (false !== \json_encode($v)) {
                $tokens['user_' . $k] = $v;
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
     * @noinspection StaticInvocationViaThisInspection*/
    #[Route('/reset', name: 'contao_backend_reset_password')]
    public function resetPasswordAction(Request $request): Response
    {
        // $request = $this->requestStack->getCurrentRequest();

        $this->framework->initialize();

        $system = $this->framework->getAdapter(System::class);
        $system->loadLanguageFile('default');
        $system->loadLanguageFile('modules');

        /** @var BackendTemplate|object $template */
        $template = new BackendTemplate('be_lost_password_reset');

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

        $userAdapter = $this->framework->getAdapter(UserModel::class);

        if (!$user = $userAdapter->findOneBy(['backendLostPasswordActivation=?'], [$token])) {
            $template->errorMessage = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['resetErrorExplanation'];

            return $template->getResponse();
        }

        if ('tl_reset_password' !== $request->request->get('FORM_SUBMIT')) {
            return $template->getResponse();
        }

        $password = $request->request->get('password');
        $confirm = $request->request->get('confirm');

        /** @var Controller $controller */
        $controller = $this->framework->getAdapter(Controller::class);

        if ($password !== $confirm)
        {
            Message::addError($GLOBALS['TL_LANG']['ERR']['passwordMatch'] ?? 'Passwords don\'t match.');

            $controller->reload();
        }

        if (\mb_strlen($password) < Config::get('minPasswordLength'))
        {
            Message::addError(\sprintf(
                $GLOBALS['TL_LANG']['ERR']['passwordLength'] ?? 'Minimum required password length is %s.',
                Config::get('minPasswordLength')
            ));

            $controller->reload();
        }

        if (\str_contains($password, $user->username))
        {
            Message::addError($GLOBALS['TL_LANG']['ERR']['passwordName'] ?? 'The password must not contain the username.');

            $controller->reload();
        }

        $table = 'tl_user';
        if (!isset($GLOBALS['TL_DCA'][$table])) {
            $controller->loadDataContainer($table);
        }

        ($saveCallback = $GLOBALS['TL_DCA']['tl_user']['fields']['password']['save_callback'] ?? null);

        if (\is_callable($saveCallback)
            || (\is_array($saveCallback) && \array_is_list($saveCallback) && \count($saveCallback) === 2))
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

        $user->pwChange = false;
        $user->backendLostPasswordActivation = '';
        $user->password = password_hash($password, \PASSWORD_DEFAULT);
        $user->save();

        Message::addConfirmation($GLOBALS['TL_LANG']['MSC']['pw_changed']
            ?? 'The password has been changed successfully.');

        $controller->redirect('contao');
    }
}