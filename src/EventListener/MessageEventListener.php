<?php

namespace HeimrichHannot\BackendLostPasswordBundle\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\Event\MessageEvent;

class MessageEventListener
{
    #[AsEventListener]
    public function __invoke(MessageEvent $event): void
    {
        return;
    }
}