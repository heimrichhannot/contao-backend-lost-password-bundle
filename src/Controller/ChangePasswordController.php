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
use Contao\CoreBundle\OptIn\OptIn;
use Contao\CoreBundle\OptIn\OptInToken;
use Contao\CoreBundle\OptIn\OptInTokenInterface;
use Contao\DC_Table;
use Contao\Email;
use Contao\Environment;
use Contao\FormPassword;
use Contao\Idna;
use Contao\Input;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Contao\Widget;
use HeimrichHannot\UtilsBundle\Util\Utils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(
    path: '%contao.backend.route_prefix%/lost-password/change',
    name: self::NAME,
    defaults: [
        '_scope' => 'backend',
    ]
)]
class ChangePasswordController extends AbstractLostPasswordController
{
    public const NAME = 'contao_backend_change_password';

    public function __construct(
        private readonly ContaoFramework        $framework,
        private readonly OptIn                  $optIn,
        private readonly TranslatorInterface    $translator,
    ) {}

    public function __invoke(Request $request): Response
    {
        $this->framework->initialize();

        $system = $this->framework->getAdapter(System::class);
        $system->loadLanguageFile('default');
        $system->loadLanguageFile('modules');

        $template = $this->createLegacyTemplate('backend/lost_password/change');
        $template->headline = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['reset'] ?? null;
        $template->explain = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['resetExplanation'] ?? null;
        $template->submitButton = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['continue'] ?? '');

        $strFormId = 'tl_reset_password';
        $submitted = $strFormId === $request->request->get('FORM_SUBMIT');

        if (!$submitted) {
            try {
                $token = $this->fetchToken($request);
                $user = $this->loadUserFromRequestToken($token);
            } catch (\Exception $e) {
                Message::addError($e->getMessage());

                $template->errorMessage = $this->translator->trans(
                    'MSC.backendLostPassword.resetErrorExplanation',
                    domain: 'contao_default'
                );
                $template->fields = [];
                return $this->createTemplateResponse($template, $request);
            }
        }

        $passwordField = $this->createPasswordField([
            'label' => &$GLOBALS['TL_LANG']['MSC']['password'],
            'name' => 'password',
        ]);
        $confirmField = $this->createPasswordField([
            'label' => &$GLOBALS['TL_LANG']['MSC']['confirm'],
            'name' => 'password_confirm',
        ]);


        $fields = [$passwordField, $confirmField];
        $template->fields = $fields;

        if (!$submitted) {
            return $this->createTemplateResponse($template, $request);
        }

        $doNotSubmit = false;
        // Initialize the widgets
        foreach ($fields as $objWidget)
        {
            // Validate the widget
            if ($submitted)
            {
                $objWidget->validate();

                if ($objWidget->hasErrors())
                {
                    $doNotSubmit = true;
                }
            }

            $widgets[] = $objWidget;
        }

        if ($doNotSubmit) {
            foreach ($widgets as $widget) {
                if ($widget->hasErrors()) {
                    Message::addError($widget->getErrorAsString());
                }
            }
            return $this->redirect($request->getUri());
        }

        if ($passwordField->value !== $confirmField->value) {
            Message::addError($GLOBALS['TL_LANG']['ERR']['passwordMatch'] ?? 'Passwords don\'t match.');
            return $this->redirect($request->getUri());
        }

        $template->hasError = $doNotSubmit;
        return $this->createTemplateResponse($template, $request);


        $password = $request->request->get('password');
        $confirm = $request->request->get('confirm');

        /** @var Controller $controller */
        $controller = $this->framework->getAdapter(Controller::class);

        if ($password !== $confirm) {
            Message::addError($GLOBALS['TL_LANG']['ERR']['passwordMatch'] ?? 'Passwords don\'t match.');

            $controller->reload();
        }

        if (\mb_strlen($password) < Config::get('minPasswordLength')) {
            Message::addError(
                \sprintf(
                    $GLOBALS['TL_LANG']['ERR']['passwordLength'] ?? 'Minimum required password length is %s.',
                    Config::get('minPasswordLength')
                )
            );

            $controller->reload();
        }

        if (\str_contains($password, $user->username)) {
            Message::addError($GLOBALS['TL_LANG']['ERR']['passwordName'] ?? 'The password must not contain the username.');

            $controller->reload();
        }

        $table = 'tl_user';
        if (!isset($GLOBALS['TL_DCA'][$table])) {
            $controller->loadDataContainer($table);
        }

        ($saveCallback = $GLOBALS['TL_DCA']['tl_user']['fields']['password']['save_callback'] ?? null);

        if (\is_callable($saveCallback)
            || (\is_array($saveCallback) && \array_is_list($saveCallback) && \count($saveCallback) === 2)) {
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
        $user->password = password_hash($password, \PASSWORD_DEFAULT);
        $user->save();

        Message::addConfirmation(
            $GLOBALS['TL_LANG']['MSC']['pw_changed']
            ?? 'The password has been changed successfully.'
        );

        $controller->redirect('contao');
    }

    private function createPasswordField(array $field): FormPassword
    {
        $field = array_merge([
            'inputType' => 'password',
            'eval' => [
                'required' => true,
                'minlength' => Config::get('minPasswordLength'),
            ],
        ], $field);

        $objWidget = new FormPassword(FormPassword::getAttributesFromDca($field, $field['name']));
        $objWidget->storeValues = true;

        return $objWidget;
    }

    private function fetchToken(Request $request): OptInTokenInterface
    {
        $tokenIdentifier = $request->query->get('token');
        if (null === $tokenIdentifier || !str_starts_with($tokenIdentifier, RequestPasswordChangeController::TOKEN_PREFIX)) {
            throw new \Exception('No token provided');
        }

        $token = $this->optIn->find($tokenIdentifier);
        if (null === $token) {
            throw new \Exception('Token for identifier not found!');
        }

        if (!$token->isValid()) {
            throw new \Exception($this->translator->trans('MSC.invalidToken', domain: 'contao_default'));
        }

        return $token;
    }

    private function loadUserFromRequestToken(OptInTokenInterface $token): UserModel
    {
        $arrRelated = $token->getRelatedRecords();

        if (\count($arrRelated) != 1 || key($arrRelated) != 'tl_user' || \count($arrIds = current($arrRelated)) != 1 || (!$userModel = UserModel::findById($arrIds[0]))) {
            throw new \Exception($this->translator->trans('MSC.invalidToken', domain: 'contao_default'));
        }

        if ($token->isConfirmed()) {
            throw new \Exception($this->translator->trans('MSC.tokenConfirmed', domain: 'contao_default'));
        }

        if ($token->getEmail() != $userModel->email) {
            throw new \Exception($this->translator->trans('MSC.tokenEmailMismatch', domain: 'contao_default'));
        }

//        $token->confirm();

        return $userModel;
    }
}

