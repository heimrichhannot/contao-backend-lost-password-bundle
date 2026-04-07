<?php

namespace HeimrichHannot\BackendLostPasswordBundle\NotificationCenter;

use Terminal42\NotificationCenterBundle\NotificationType\NotificationTypeInterface;
use Terminal42\NotificationCenterBundle\Token\Definition\AnythingTokenDefinition;
use Terminal42\NotificationCenterBundle\Token\Definition\Factory\TokenDefinitionFactoryInterface;
use Terminal42\NotificationCenterBundle\Token\Definition\TextTokenDefinition;

class LostPasswordNotificationType implements NotificationTypeInterface
{
    public const NAME = 'user_password';

    public function __construct(private TokenDefinitionFactoryInterface $factory)
    {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getTokenDefinitions(): array
    {
        return [
            $this->factory->create(AnythingTokenDefinition::class, 'user_*', 'user.user_*'),
            $this->factory->create(AnythingTokenDefinition::class, 'recipient_email', 'recipient.recipient_email'),
            $this->factory->create(TextTokenDefinition::class, 'domain', 'domain'),
            $this->factory->create(TextTokenDefinition::class, 'link', 'link'),
        ];
    }
}