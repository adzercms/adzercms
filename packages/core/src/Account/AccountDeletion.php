<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Account;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Ui\CabinetEntry;
use AdzerCMS\Core\Confirmation\ConfirmationService;
use AdzerCMS\Core\Entity\Confirmation;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Notice\Notifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

use function Symfony\Component\Translation\t;

final readonly class AccountDeletion
{
    /** @param iterable<AccountPolicy> $policies */
    public function __construct(
        #[AutowireIterator(AccountPolicy::class)]
        private iterable $policies,
        private ConfirmationService $confirmations,
        private Notifier $notifier,
        private EntityManagerInterface $em,
    ) {
    }

    public function offered(): bool
    {
        foreach ($this->policies as $policy) {
            if ($policy->deletion()) {
                return true;
            }
        }

        return false;
    }

    public function offeredTo(User $user): bool
    {
        return $this->offered() && Role::Admin !== $user->getRole();
    }

    public function refusal(User $user): ?Refusal
    {
        if (Role::Admin === $user->getRole()) {
            return new Refusal(t('core.deletion.operator'));
        }

        if (null === $user->getEmail() || !$this->notifier->reachable(ContactKind::Email)) {
            return new Refusal(t('core.deletion.no_mail'), CabinetEntry::Profile->route());
        }

        foreach ($this->policies as $policy) {
            $refusal = $policy->deletionRefusal($user);
            if (null !== $refusal) {
                return $refusal;
            }
        }

        return null;
    }

    public function request(User $user): ?Refusal
    {
        return $this->refusal($user) ?? match ($this->confirmations->requestDeletion($user)) {
            ContactOutcome::Sent => null,
            ContactOutcome::TooOften => new Refusal(t('core.confirmation.too_often')),
            default => new Refusal(t('core.deletion.unsent')),
        };
    }

    public function confirm(Confirmation $confirmation): ?Refusal
    {
        $user = $confirmation->requireUser();
        $refusal = $this->refusal($user);

        if (null !== $refusal) {
            return $refusal;
        }

        $confirmation->use();
        $user->delete();
        $this->em->flush();

        return null;
    }
}
