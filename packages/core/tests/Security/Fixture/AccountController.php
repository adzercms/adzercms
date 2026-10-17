<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Security\Fixture;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Security\CsrfGuard;
use AdzerCMS\Core\Security\Entry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
final class AccountController
{
    #[Route('/fixture/account', name: 'fixture_account')]
    #[IsGranted(Role::USER)]
    public function account(#[CurrentUser] User $user): Response
    {
        return new Response($user->getUserIdentifier());
    }

    #[Route('/fixture/account/password', name: 'fixture_account_password', defaults: [CsrfGuard::OWN => true], methods: ['POST'])]
    #[IsGranted(Role::USER)]
    public function password(#[CurrentUser] User $user, EntityManagerInterface $em): Response
    {
        $user->setPassword('own-change-hash');
        $em->flush();

        return new Response($user->getUserIdentifier());
    }

    #[Route('/fixture/entry/{id}', name: 'fixture_entry')]
    public function entry(int $id, EntityManagerInterface $em, Entry $entry): Response
    {
        return $entry->open($em->find(User::class, $id) ?? throw new \LogicException('no record '.$id));
    }

    #[Route('/fixture/admin', name: 'fixture_admin')]
    #[IsGranted(Role::ADMIN)]
    public function admin(#[CurrentUser] User $user): Response
    {
        return new Response($user->getUserIdentifier());
    }
}
