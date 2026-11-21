<?php

declare(strict_types=1);

namespace AdzerCMS\Builder;

use AdzerCMS\Builder\Entity\Project;
use AdzerCMS\Builder\Entity\ProjectTransfer;
use AdzerCMS\Builder\Repository\ProjectRepository;
use AdzerCMS\Builder\Repository\ProjectTransferRepository;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Account\LoginNames;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Notice\Notifier;
use AdzerCMS\Core\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

use function Symfony\Component\Clock\now;
use function Symfony\Component\Translation\t;

final readonly class Transfers
{
    public const TERM = '+7 days';
    public const EMAIL = 'email';

    public function __construct(
        private ProjectTransferRepository $transfers,
        private ProjectRepository $projects,
        private UserRepository $users,
        private Notifier $notifier,
        private LoginNames $logins,
        private EntityManagerInterface $em,
    ) {
    }

    public function offer(Project $project, string $email): ?Refusal
    {
        $address = ContactKind::Email->normalize($email);
        $recipient = null === $address ? null : $this->users->findOneBy(['email' => $address, 'deletedAt' => null]);
        if (null === $recipient) {
            return new Refusal(t('builder.refusal.recipient', ['email' => trim($email)]));
        }
        if ($recipient === $project->getOwner()) {
            return new Refusal(t('builder.refusal.recipient_owner'));
        }

        $former = $this->transfers->findOneBy(['project' => $project]);
        if (null !== $former) {
            $this->close($former, $former->getExpires() > now() ? TransferOutcome::Cancelled : TransferOutcome::Expired, $project->getOwner());
        }
        $transfer = new ProjectTransfer($project, $recipient, now()->modify(self::TERM));
        $this->em->persist($transfer);
        $this->em->flush();
        $this->notifier->notify($recipient, BuilderEvent::Offer, [
            'name' => $project->getName(),
            'sender' => $this->logins->of($project->getOwner()),
            'expires' => $transfer->getExpires(),
        ], self::item($transfer, BuilderEvent::Offer->value));

        return null;
    }

    public function cancel(ProjectTransfer $transfer): void
    {
        $this->close($transfer, TransferOutcome::Cancelled, $transfer->getProject()->getOwner());
    }

    public function accept(ProjectTransfer $transfer): ?Refusal
    {
        $recipient = $transfer->getRecipient();
        if ($this->projects->counted($recipient) >= Projects::PROJECTS) {
            return new Refusal(t('builder.refusal.projects', ['limit' => Projects::PROJECTS]));
        }

        $sender = $transfer->getProject()->getOwner();
        $transfer->getProject()->handOver($recipient);
        $this->close($transfer, TransferOutcome::Accepted, $sender);

        return null;
    }

    public function sweep(): int
    {
        $expired = $this->transfers->expired();
        foreach ($expired as $transfer) {
            $this->close($transfer, TransferOutcome::Expired, $transfer->getProject()->getOwner());
        }

        return \count($expired);
    }

    private function close(ProjectTransfer $transfer, TransferOutcome $outcome, User $sender): void
    {
        $item = self::item($transfer, $outcome->value);
        $this->em->remove($transfer);
        $this->em->flush();
        foreach ($outcome->notified($sender, $transfer->getRecipient()) as $user) {
            $this->notifier->notify($user, BuilderEvent::Outcome, ['name' => $transfer->getProject()->getName(), 'outcome' => $outcome->label()], $item);
        }
    }

    private static function item(ProjectTransfer $transfer, string $step): string
    {
        return implode("\0", ['builder', (string) $transfer->getId(), $step]);
    }
}
