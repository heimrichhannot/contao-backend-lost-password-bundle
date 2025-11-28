<?php

namespace HeimrichHannot\BackendLostPasswordBundle\EventListener\Contao;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use HeimrichHannot\BackendLostPasswordBundle\Manager\BackendLostPasswordManager;
use Symfony\Component\DomCrawler\Crawler;
use Twig\Environment as TwigEnvironment;

#[AsHook('parseBackendTemplate')]
readonly class ParseTemplateListener
{
    public function __construct(
        private BackendLostPasswordManager $backendLostPasswordManager,
        private TwigEnvironment            $twig,
        private array                      $bundleConfig
    ) {}

    public function __invoke(string $buffer, string $template): string
    {
        if ($template !== 'be_login') {
            return $buffer;
        }

        if (!\filter_var(
            $this->bundleConfig['add_to_template'],
            \FILTER_VALIDATE_BOOLEAN,
            \FILTER_NULL_ON_FAILURE
        )) {
            return $buffer;
        }

        $link = $this->twig->render(
            name: '@HeimrichHannotBackendLostPassword/link_request_reset.html.twig',
            context: [
                'url' => $this->backendLostPasswordManager->getRequestPasswordResetUrl(),
            ],
        );

        return $this->insertLinkAfterPasswordWidget($buffer, $link);
    }

    private function insertLinkAfterPasswordWidget(string $buffer, string $link): string
    {
        if (\trim($buffer) === '') {
            return $buffer;
        }

        $crawler = new Crawler($buffer);

        $passwordNode = $crawler
            ->filter('form.tl_login_form div.widget-password')
            ->first();

        if ($passwordNode->count() === 0) {
            return $buffer;
        }

        $domElement = $passwordNode->getNode(0);

        if (!$domElement instanceof \DOMElement) {
            return $buffer;
        }

        $document = $domElement->ownerDocument;

        if (!$document instanceof \DOMDocument) {
            return $buffer;
        }

        // Build fragment from rendered Twig link HTML
        $fragment = $document->createDocumentFragment();

        if (!$fragment->appendXML($link)) {
            // Malformed HTML in $link – fail graceful and return original buffer
            return $buffer;
        }

        $parent      = $domElement->parentNode;
        $nextSibling = $domElement->nextSibling;

        if ($parent === null) {
            return $buffer;
        }

        if (!$nextSibling) {
            $parent->appendChild($fragment);
        } else {
            $parent->insertBefore($fragment, $nextSibling);
        }

        return $document->saveHTML();
    }
}