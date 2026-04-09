<?php

namespace HeimrichHannot\BackendLostPasswordBundle\Routing\Matcher;

use HeimrichHannot\BackendLostPasswordBundle\Controller\ChangePasswordController;
use HeimrichHannot\BackendLostPasswordBundle\Controller\RequestPasswordChangeController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;

class ResetPasswordMatcher implements RequestMatcherInterface
{
    public function matches(Request $request): bool
    {
        return in_array(
            $request->attributes->get('_route'),
            [RequestPasswordChangeController::NAME, ChangePasswordController::NAME]
        );
    }
}
