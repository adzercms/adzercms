<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Account;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

use function Symfony\Component\Translation\t;

final readonly class UserRemoval
{
    /** @param iterable<AccountPolicy> $policies */
    public function __construct(
        #[AutowireIterator(AccountPolicy::class)]
        private iterable $policies,
        private EntityManagerInterface $em,
    ) {
    }

    public function kept(User $user): ?Refusal
    {
        foreach ($this->policies as $policy) {
            $refusal = $policy->removalRefusal($user);
            if (null !== $refusal) {
                return $refusal;
            }
        }

        return null;
    }

    public function refusal(User $user, User $operator): ?Refusal
    {
        return $user === $operator ? new Refusal(t('core.user.remove_self')) : $this->kept($user);
    }

    public function archive(User $user): void
    {
        $user->setStatus(Status::Archived);
        $this->em->flush();

        foreach ($this->policies as $policy) {
            $policy->archived($user);
        }

        $this->changed($user);
    }

    public function changed(User $user): void
    {
        $this->em->flush();

        foreach ($this->policies as $policy) {
            $policy->changed($user);
        }
    }

    public function remove(User $user): void
    {
        foreach ($this->policies as $policy) {
            $policy->removing($user);
        }

        $this->em->remove($user);
        $this->em->flush();
    }
}
