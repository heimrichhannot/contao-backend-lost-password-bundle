<?php

namespace HeimrichHannot\BackendLostPasswordBundle\EventListener;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(KernelEvents::REQUEST)]
readonly class AddAssetsListener
{
    public function __construct(
        private ScopeMatcher $scopeMatcher
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $this->scopeMatcher->isBackendRequest($request)
            ? $this->addBackendAssets()
            : $this->addFrontendAssets()
        ;
    }

    protected function addBackendAssets(): void
    {
        $GLOBALS['TL_CSS'][] = 'bundles/heimrichhannotbackendlostpassword/backend/styles.css';
    }

    protected function addFrontendAssets(): void
    {
        // $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/heimrichhannotbackendlostpassword/frontend/main.js';
    }
}
