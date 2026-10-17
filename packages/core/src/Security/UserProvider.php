<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Security;

use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Repository\UserRepository;
use AdzerCMS\Core\Session\Logins;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/** @implements UserProviderInterface<User> */
final class UserProvider implements UserProviderInterface
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly Logins $logins,
        private readonly RequestStack $requests,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): User
    {
        return $this->users->byLogin($identifier) ?? throw new UserNotFoundException();
    }

    public function refreshUser(UserInterface $user): User
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException();
        }

        $fresh = $this->users->find($user->getId()) ?? throw new UserNotFoundException();
        $request = $this->requests->getMainRequest();
        if (null !== $request && !$this->logins->live($request)) {
            throw new UserNotFoundException();
        }

        return $fresh;
    }

    public function supportsClass(string $class): bool
    {
        return is_a($class, User::class, true);
    }
}
