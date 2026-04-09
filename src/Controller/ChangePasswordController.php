<?php

/**
 * @copyright Heimrich & Hannot GmbH, 2024
 * @license   LGPL-3.0-or-later
 */

namespace HeimrichHannot\BackendLostPasswordBundle\Controller;

use Contao\BackendUser;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\OptIn\OptIn;
use Contao\CoreBundle\OptIn\OptInTokenInterface;
use Contao\DC_Table;
use Contao\Message;
use Contao\Password;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Contao\Versions;
use HeimrichHannot\UtilsBundle\Util\Utils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
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
        private readonly ContaoFramework $framework,
        private readonly OptIn $optIn,
        private readonly TranslatorInterface $translator,
        private readonly Utils $utils,
        private readonly PasswordHasherFactoryInterface $passwordHasherFactory,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->framework->initialize();

        $system = $this->framework->getAdapter(System::class);
        $system->loadLanguageFile('default');
        $system->loadLanguageFile('modules');
        Controller::loadDataContainer('tl_user');

        $template = $this->createLegacyTemplate('backend/lost_password/change');
        $template->headline = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['reset'] ?? null;
        $template->explain = $GLOBALS['TL_LANG']['MSC']['backendLostPassword']['resetExplanation'] ?? null;
        $template->submitButton = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['continue'] ?? '');

        try {
            $token = $this->fetchToken($request);
            $user = $this->loadUserFromRequestToken($token);
        } catch (\Exception $e) {
            Message::addError($e->getMessage());

            $template->explain = $this->translator->trans(
                'MSC.backendLostPassword.resetErrorExplanation',
                domain: 'contao_default'
            );
            $template->fields = [];

            return $this->createTemplateResponse($template, $request);
        }

        $submitted = false;
        $session = $request->getSession();
        if ($request->isMethod(Request::METHOD_POST) && $request->request->has('FORM_SUBMIT')) {
            $setPwToken = $session->get('setPasswordToken');
            $submitted = $request->request->get('FORM_SUBMIT') === $setPwToken;
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

        $strToken = md5(uniqid(mt_rand(), true));
        $session->set('setPasswordToken', $strToken);
        $template->formId = $strToken;

        if (!$submitted) {
            return $this->createTemplateResponse($template, $request);
        }

        $newPassword = $request->request->get('password');
        $passwordHasher = $this->passwordHasherFactory->getPasswordHasher(BackendUser::class);

        try {
            $this->validatePassword($newPassword, $request, $fields, $user, $passwordHasher);
        } catch (\Exception $e) {
            Message::addError($e->getMessage());

            return $this->createTemplateResponse($template, $request);
        }

        $objVersions = new Versions('tl_user', $user->id);
        $objVersions->setUsername($user->username);
        $objVersions->setEditUrl($this->generateUrl('contao_backend', [
            'do' => 'user',
            'act' => 'edit',
            'id' => $user->id
        ]));
        $objVersions->initialize();

        $dc = $this->createDataContainerObject($user);

        if (is_array($GLOBALS['TL_DCA']['tl_user']['fields']['password']['save_callback'] ?? null)) {
            foreach ($GLOBALS['TL_DCA']['tl_user']['fields']['password']['save_callback'] as $callback) {
                $result = $this->utils->dca()->executeCallback(
                    $callback,
                    $newPassword,
                    $dc
                );
                if (is_string($result) && !empty($result)) {
                    $newPassword = $result;
                }
            }
        }

        $user->pwChange = false;
        $user->password = $passwordHasher->hash($newPassword);
        $user->save();

        $token->confirm();

        $objVersions->create();

        Message::addConfirmation(
            $GLOBALS['TL_LANG']['MSC']['pw_changed']
            ?? 'The password has been changed successfully.'
        );

        return $this->redirect($this->generateUrl('contao_backend_login'));
    }

    private function validatePassword(
        #[\SensitiveParameter] string $newPassword,
        Request $request,
        array $fields,
        UserModel $user,
        PasswordHasherInterface $passwordHasher,
    ): void {
        if ($newPassword !== $request->request->get('password_confirm')) {
            throw new \Exception($this->translator->trans('ERR.passwordMatch', domain: 'contao_default'));
        }

        $doNotSubmit = false;
        foreach ($fields as $objWidget) {
            $objWidget->validate();

            if ($objWidget->hasErrors()) {
                $doNotSubmit = true;
            }
        }

        if ($doNotSubmit) {
            $errors = '';
            foreach ($fields as $widget) {
                if ($widget->hasErrors()) {
                    $errors .= $widget->getErrorAsString();
                }
            }
            throw new \Exception($errors);
        }

        if ($newPassword == $user->username) {
            throw new \Exception($this->translator->trans('ERR.passwordName', domain: 'contao_default'));
        }

        if ($passwordHasher->verify($user->password, $newPassword)) {
            throw new \Exception($this->translator->trans('MSC.pw_change', domain: 'contao_default'));
        }
    }

    private function createPasswordField(array $field): Password
    {
        $field = array_merge([
            'inputType' => 'password',
            'eval' => [
                'required' => true,
                'minlength' => Config::get('minPasswordLength'),
            ],
        ], $field);

        $objWidget = new Password(Password::getAttributesFromDca($field, $field['name']));
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

        if (1 != \count($arrRelated) || 'tl_user' != key($arrRelated) || 1 != \count($arrIds = current($arrRelated)) || (!$userModel = UserModel::findById($arrIds[0]))) {
            throw new \Exception($this->translator->trans('MSC.invalidToken', domain: 'contao_default'));
        }

        if ($token->isConfirmed()) {
            throw new \Exception($this->translator->trans('MSC.tokenConfirmed', domain: 'contao_default'));
        }

        if ($token->getEmail() != $userModel->email) {
            throw new \Exception($this->translator->trans('MSC.tokenEmailMismatch', domain: 'contao_default'));
        }

        return $userModel;
    }

    public function createDataContainerObject(UserModel $user): DC_Table
    {
        return new class($user) extends DC_Table {
            public function __construct(
                private readonly UserModel $user
            ) {
                $this->intId = $user->id;
                $this->strTable = $user::getTable();
                $this->objActiveRecord = $this->user;
            }

            public function getCurrentRecord(int|string|null $id = null, ?string $table = null): ?array
            {
                if (is_string($table) && $this->user::getTable() !== $table) {
                    return null;
                }

                if (null !== $id && (int) $id !== $this->user->id) {
                    return null;
                }

                return $this->user->row();
            }

            protected function row()
            {
                return $this->user->row();
            }
        };
    }
}
