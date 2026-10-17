<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Security;

use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/** @implements UserProviderInterface<User> */
final class RecordProvider implements UserProviderInterface
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserProvider $logins,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): User
    {
        $user = ctype_digit($identifier) ? $this->users->find((int) $identifier) : null;

        return $user ?? throw new UserNotFoundException();
    }

    public function refreshUser(UserInterface $user): User
    {
        return $this->logins->refreshUser($user);
    }

    public function supportsClass(string $class): bool
    {
        return $this->logins->supportsClass($class);
    }
}
