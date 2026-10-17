<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Security;

use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Session\Logins;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class LoginListener
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Logins $logins,
    ) {
    }

    #[AsEventListener(priority: -128)]
    public function onLogin(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if ($user instanceof User) {
            $user->enter();
            $this->logins->open($event->getRequest(), $user);
            $this->em->flush();
        }
    }

    #[AsEventListener(priority: 32)]
    public function onLogout(LogoutEvent $event): void
    {
        $this->logins->close($event->getRequest());
    }
}
