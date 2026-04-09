<?php

/**
 * @copyright Heimrich & Hannot GmbH, 2024
 * @license   LGPL-3.0-or-later
 */

namespace HeimrichHannot\BackendLostPasswordBundle\Controller;

use Symfony\Component\Routing\Attribute\Route;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\OptIn\OptIn;
use Contao\CoreBundle\OptIn\OptInTokenInterface;
use Contao\DC_Table;
use Contao\FormPassword;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Contao\Versions;
use HeimrichHannot\UtilsBundle\Util\Utils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        private readonly Utils                $utils,
    ) {}

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

        if ($request->request->get('password') !== $request->request->get('password_confirm')) {
            Message::addError($GLOBALS['TL_LANG']['ERR']['passwordMatch'] ?? 'Passwords don\'t match.');
            return $this->redirect($request->getUri());
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

        // Initialize the versioning (see #8301)
        $objVersions = new Versions('tl_user', $user->id);
        $objVersions->setUsername($user->username);
        $objVersions->setEditUrl($this->generateUrl('contao_backend', ['do' => 'user', 'act' => 'edit', 'id' => $user->id]));
        $objVersions->initialize();

        $dc = $this->createDataContainerObject($user);
        $pw = $passwordField->value;

        $this->utils->dca()->executeCallback(
            $GLOBALS['TL_DCA']['tl_user']['fields']['password']['save_callback'] ?? null,
            $pw,
            $dc
        );

        $user->pwChange = false;
        $user->password = $pw;
        $user->save();

        $token->confirm();

        // Create a new version
        if ($GLOBALS['TL_DCA']['tl_user']['config']['enableVersioning'] ?? null)
        {
            $objVersions->create();
        }

        Message::addConfirmation(
            $GLOBALS['TL_LANG']['MSC']['pw_changed']
            ?? 'The password has been changed successfully.'
        );

        return $this->redirect($this->generateUrl('contao_backend_login'));
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

    public function createDataContainerObject(UserModel $user): DC_Table
    {
        return new class ($user) extends DC_Table {
            public function __construct(private readonly UserModel $user)
            {
                $this->intId = $user->id;
                $this->strTable = $user::getTable();
                $this->objActiveRecord = $this->user;
            }

            public function getCurrentRecord(int|string|null $id = null, ?string $table = null): array|null
            {
                if (is_string($table) && $this->user::getTable() !== $table) {
                    return null;
                }

                if (null !== $id && (int)$id !== $this->user->id) {
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

