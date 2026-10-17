<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Security;

use AdzerCMS\Core\Account\Status;
use AdzerCMS\Core\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!$user instanceof User || Status::Active === $user->getStatus()) {
            return;
        }

        throw $user->isDeleted() ? new RecordDeleted() : new CustomUserMessageAccountStatusException('core.account.stopped');
    }
}
