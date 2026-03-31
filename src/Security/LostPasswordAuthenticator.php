<?php

namespace HeimrichHannot\BackendLostPasswordBundle\Security;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class LostPasswordAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly ScopeMatcher $scopeMatcher,
    ) {}

    public function supports(Request $request): ?bool
    {
        if (!$this->scopeMatcher->isBackendRequest($request)) {
            return false;
        }

        return $request->attributes->get('_route') === 'contao_be_lost_password';
    }

    public function authenticate(Request $request): Passport
    {
        // TODO: Implement authenticate() method.
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // TODO: Implement onAuthenticationSuccess() method.
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // TODO: Implement onAuthenticationFailure() method.
    }
}