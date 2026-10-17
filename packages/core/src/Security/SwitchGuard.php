<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Security;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Entity\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Event\SwitchUserEvent;
use Symfony\Component\Security\Http\Firewall\SwitchUserListener;

#[AsEventListener]
final class SwitchGuard
{
    public function __invoke(SwitchUserEvent $event): void
    {
        $target = $event->getTargetUser();
        $exit = SwitchUserListener::EXIT_VALUE === $event->getRequest()->query->get('_switch_user');

        if (!$exit && $target instanceof User && Role::Admin === $target->getRole()) {
            throw new AccessDeniedException('switching to an administrator record is not allowed');
        }
    }
}
