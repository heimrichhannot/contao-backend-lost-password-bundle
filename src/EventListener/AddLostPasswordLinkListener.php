<?php

namespace HeimrichHannot\BackendLostPasswordBundle\EventListener;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Event\MenuEvent;
use Contao\Message;
use Contao\Template;
use HeimrichHannot\BackendLostPasswordBundle\Controller\RequestPasswordChangeController;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

readonly class AddLostPasswordLinkListener
{
    public function __construct(
        private TranslatorInterface $translator,
        private Environment         $twig,
        private array               $bundleConfig,
        private RouterInterface     $router,
    ) {}

    #[AsEventListener(priority: -192)]
    public function onMenuEvent(MenuEvent $event): void
    {
        if (true !== $this->bundleConfig['add_to_template']) {
            return;
        }

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
            ->setUri($this->requestPasswordUrl())
            ->setAttribute('class', 'lost-password')
            ->setExtra('translation_domain', false);

        $tree->addChild($node);
    }

    #[AsHook('parseTemplate')]
    public function onParseTemplate(Template $template): void
    {
        if (true !== $this->bundleConfig['add_to_template']) {
            return;
        }

        if (version_compare(ContaoCoreBundle::getVersion(), '5.7', '>=')) {
            return;
        }

        if ('be_login' !== $template->getName()) {
            return;
        }

        Message::addInfo('<a href="'.$this->requestPasswordUrl().'">'.$this->translator->trans('huh.backend_lost_password.misc.lost_password').'</a>');

        $messages = $this->twig->render(
            name: '@Contao/backend/lost_password/link.html.twig',
            context: [
                'url' => $this->requestPasswordUrl(),
            ],
        );

        $messages .= ($template->messages ?? '');
        $template->messages = $messages;
    }

    private function requestPasswordUrl(): string
    {
        return $this->router->generate(RequestPasswordChangeController::NAME, referenceType: RouterInterface::ABSOLUTE_URL);
    }
}