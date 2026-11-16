<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

trait Accounts
{
    protected const PASSWORD = 'record-password';

    protected function record(string $phone, ?string $email = null): User
    {
        $user = (new User())->setPhone($phone)->setEmail($email);
        $user->setPassword(self::service(UserPasswordHasherInterface::class)->hashPassword($user, self::PASSWORD));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function admin(string $phone = '79990000001'): User
    {
        $admin = $this->record($phone)->setRole(Role::Admin);
        $this->em->flush();
        $this->client->loginUser($admin);

        return $admin;
    }
}
