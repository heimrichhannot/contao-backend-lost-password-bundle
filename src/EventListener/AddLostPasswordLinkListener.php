<?php

namespace HeimrichHannot\BackendLostPasswordBundle\EventListener;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Event\MenuEvent;
use Contao\Template;
use HeimrichHannot\BackendLostPasswordBundle\Manager\BackendLostPasswordManager;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class AddLostPasswordLinkListener
{
    public function __construct(
        private readonly BackendLostPasswordManager   $backendLostPasswordManager,
        private readonly TranslatorInterface $translator,
        private readonly Environment         $twig,
    ) {}

    #[AsEventListener(priority: -192)]
    public function onMenuEvent(MenuEvent $event): void
    {
        if (version_compare(ContaoCoreBundle::getVersion(), '5.7', '<')) {
            return;
        }

        $tree = $event->getTree();

        if ('loginMenu' !== $tree->getName()) {
            return;
        }

        $factory = $event->getFactory();

        $node = $factory
            ->createItem('lost-password')
            ->setLabel($this->translator->trans('huh.backend_lost_password.misc.lost_password'))
            ->setUri($this->backendLostPasswordManager->getRequestPasswordResetUrl())
            ->setAttribute('class', 'lost-password')
            ->setExtra('translation_domain', false);

        $tree->addChild($node);
    }

    #[AsHook('parseTemplate')]
    public function onParseTemplate(Template $template): void
    {
        if (version_compare(ContaoCoreBundle::getVersion(), '5.7', '>=')) {
            return;
        }

        if ('be_login' !== $template->getName()) {
            return;
        }

        $messages = $this->twig->render(
            name: '@Contao/backend/lost_password_link.html.twig',
            context: [
                'url' => $this->backendLostPasswordManager->getRequestPasswordResetUrl(),
            ],
        );

        $messages .= ($template->messages ?? '');
        $template->messages = $messages;
    }
}