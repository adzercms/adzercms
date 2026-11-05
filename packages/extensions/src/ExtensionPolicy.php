<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Account\AccountPolicy;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Extensions\Entity\ExtensionAuthor;
use AdzerCMS\Extensions\Repository\ExtensionRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ExtensionPolicy implements AccountPolicy
{
    public function __construct(
        private ExtensionRepository $extensions,
        private EntityManagerInterface $em,
    ) {
    }

    public function registration(): bool
    {
        return false;
    }

    public function registered(User $user): void
    {
    }

    public function deletion(): bool
    {
        return false;
    }

    public function deletionRefusal(User $user): ?Refusal
    {
        return null;
    }

    public function removalRefusal(User $user): ?Refusal
    {
        return null;
    }

    public function archived(User $user): void
    {
    }

    public function changed(User $user): void
    {
    }

    public function removing(User $user): void
    {
        $author = $this->em->find(ExtensionAuthor::class, $user->getId());
        foreach (null === $author ? [] : $this->extensions->findBy(['author' => $author]) as $extension) {
            $this->em->remove($extension);
        }
    }
}
