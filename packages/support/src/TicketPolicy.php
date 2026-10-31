<?php

declare(strict_types=1);

namespace AdzerCMS\Support;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Account\AccountPolicy;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Support\Repository\TicketAttachmentRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class TicketPolicy implements AccountPolicy
{
    public function __construct(
        private TicketAttachmentRepository $attachments,
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
        foreach ($this->attachments->ofTicketsOf($user) as $attachment) {
            $this->em->remove($attachment);
        }
    }
}
