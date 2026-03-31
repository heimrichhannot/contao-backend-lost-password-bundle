<?php

namespace HeimrichHannot\BackendLostPasswordBundle\Routing\Matcher;

use HeimrichHannot\BackendLostPasswordBundle\Controller\RequestPasswordFormController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;

class ResetPasswordMatcher implements RequestMatcherInterface
{

    public function matches(Request $request): bool
    {
        return $request->attributes->get('_route') === RequestPasswordFormController::NAME;
    }
}