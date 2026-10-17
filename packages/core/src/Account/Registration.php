<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Account;

use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class Registration
{
    /** @param iterable<AccountPolicy> $policies */
    public function __construct(
        private UserRepository $users,
        private Consents $consents,
        #[AutowireIterator(AccountPolicy::class)]
        private iterable $policies,
        private EntityManagerInterface $em,
    ) {
    }

    public function offered(): bool
    {
        foreach ($this->policies as $policy) {
            if ($policy->registration()) {
                return true;
            }
        }

        return false;
    }

    public function open(?string $phone = null, ?string $email = null): User
    {
        $user = (new User())->setStatus(Status::Active);

        if (null !== $phone && null === $this->users->findOneBy(['phone' => $phone])) {
            $user->setPhone($phone);
        }
        if (null !== $email && null === $this->users->findOneBy(['email' => $email])) {
            $user->setEmail($email);
        }

        $this->em->persist($user);
        $this->em->flush();
        $this->consents->accept($user);

        foreach ($this->policies as $policy) {
            $policy->registered($user);
        }

        return $user;
    }
}
