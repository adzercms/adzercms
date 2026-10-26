<?php

declare(strict_types=1);

namespace AdzerCMS\Support;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Attachment\Attachments;
use AdzerCMS\Core\Entity\StoredFile;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Notice\Notifier;
use AdzerCMS\Support\Entity\Ticket;
use AdzerCMS\Support\Entity\TicketAttachment;
use AdzerCMS\Support\Entity\TicketMessage;
use AdzerCMS\Support\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;

use function Symfony\Component\Clock\now;
use function Symfony\Component\Translation\t;

final readonly class Tickets
{
    private const SUBJECT_MAX = 190;

    public function __construct(
        private TicketRepository $tickets,
        private SupportSettings $support,
        private Attachments $attachments,
        private Notifier $notifier,
        private EntityManagerInterface $em,
    ) {
    }

    /** @param array<mixed> $files */
    public function open(User $user, string $subject, string $department, string $body, array $files = []): Ticket|Refusal
    {
        $subject = trim($subject);
        $body = trim($body);

        if ('' === $subject) {
            return new Refusal(t('support.refusal.subject'));
        }
        if ('' === $body) {
            return new Refusal(t('support.refusal.body'));
        }
        if (!\in_array($department, $this->support->departments(), true)) {
            return new Refusal(t('support.refusal.department'));
        }

        $kept = $this->receive($files);
        if ($kept instanceof Refusal) {
            return $kept;
        }

        $moment = now();
        $ticket = new Ticket($user, mb_substr($subject, 0, self::SUBJECT_MAX), $department, $moment);
        $this->em->persist($ticket);

        $this->attach($this->say($ticket, $user, false, $body, $moment), $kept);
        $this->em->flush();

        return $ticket;
    }

    /** @param array<mixed> $files */
    public function reply(Ticket $ticket, ?User $author, bool $fromStaff, string $body, array $files = []): ?Refusal
    {
        if ($ticket->getStatus()->isClosed()) {
            return new Refusal(t('support.refusal.closed'));
        }

        $body = trim($body);
        if ('' === $body) {
            return new Refusal(t('support.refusal.body'));
        }

        $kept = $this->receive($files);
        if ($kept instanceof Refusal) {
            return $kept;
        }

        $moment = now();
        $this->attach($this->say($ticket, $author, $fromStaff, $body, $moment), $kept);
        $ticket->setStatus($fromStaff ? TicketStatus::WaitingUser : TicketStatus::WaitingStaff);
        $this->em->flush();

        if ($fromStaff) {
            $this->notifier->notify($ticket->getUser(), SupportEvent::TicketReply, [
                'number' => $ticket->getId(),
                'subject' => $ticket->getSubject(),
            ]);
        }

        return null;
    }

    /**
     * @param array<mixed> $files
     * @param list<int>    $dropped
     */
    public function edit(TicketMessage $message, string $body, array $files = [], array $dropped = []): ?Refusal
    {
        if ($message->getTicket()->getStatus()->isClosed()) {
            return new Refusal(t('support.refusal.closed'));
        }

        $body = trim($body);
        if ('' === $body) {
            return new Refusal(t('support.refusal.body'));
        }

        $kept = $this->receive($files);
        if ($kept instanceof Refusal) {
            return $kept;
        }

        foreach ($message->getAttachments() as $attachment) {
            if (\in_array($attachment->getId(), $dropped, true)) {
                $message->getAttachments()->removeElement($attachment);
                $this->em->remove($attachment);
            }
        }

        $this->attach($message, $kept);
        $message->edit($body, now());
        $this->em->flush();

        return null;
    }

    public function remove(TicketMessage $message): ?Refusal
    {
        if ($message->getTicket()->getStatus()->isClosed()) {
            return new Refusal(t('support.refusal.closed'));
        }

        $message->getTicket()->getMessages()->removeElement($message);
        $this->em->remove($message);
        $this->em->flush();

        return null;
    }

    public function drop(Ticket $ticket): void
    {
        $this->em->remove($ticket);
        $this->em->flush();
    }

    public function close(Ticket $ticket): void
    {
        if ($ticket->getStatus()->isClosed()) {
            return;
        }

        $ticket->close(now());
        $this->em->flush();
    }

    public function autoClose(\DateTimeImmutable $moment): int
    {
        $days = $this->support->autoCloseDays();
        if (0 === $days) {
            return 0;
        }

        $closed = [];
        foreach ($this->tickets->staleWaiting($moment->modify(sprintf('-%d days', $days))) as $ticket) {
            $ticket->close($moment);
            $closed[(int) $ticket->getUser()->getId()][] = $ticket;
        }

        if ([] === $closed) {
            return 0;
        }

        $this->em->flush();

        foreach ($closed as $tickets) {
            $this->notifier->notify($tickets[0]->getUser(), SupportEvent::TicketClosed, ['tickets' => $tickets, 'days' => $days]);
        }

        return array_sum(array_map(\count(...), $closed));
    }

    public function waiting(?User $user): int
    {
        return $this->tickets->countByStatus(
            $user,
            null === $user ? [TicketStatus::Open, TicketStatus::WaitingStaff] : [TicketStatus::WaitingUser],
        );
    }

    private function say(Ticket $ticket, ?User $author, bool $fromStaff, string $body, \DateTimeImmutable $moment): TicketMessage
    {
        $message = new TicketMessage($ticket, $author, $fromStaff, $body, $moment);
        $ticket->touch($moment);
        $this->em->persist($message);

        return $message;
    }

    /**
     * @param array<mixed> $files
     *
     * @return list<StoredFile>|Refusal
     */
    private function receive(array $files): array|Refusal
    {
        return $this->attachments->receive($files, $this->support->attachments(), TicketAttachment::class);
    }

    /** @param list<StoredFile> $files */
    private function attach(TicketMessage $message, array $files): void
    {
        foreach ($files as $file) {
            $this->em->persist(new TicketAttachment($message, $file));
        }
    }
}
