<?php

declare(strict_types=1);

namespace AdzerCMS\Support;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Notice\Notifier;
use AdzerCMS\Support\Entity\Ticket;
use AdzerCMS\Support\Entity\TicketAttachment;
use AdzerCMS\Support\Entity\TicketMessage;
use AdzerCMS\Support\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function Symfony\Component\Clock\now;
use function Symfony\Component\Translation\t;

final class Tickets
{
    private const SUBJECT_MAX = 190;

    public function __construct(
        private readonly TicketRepository $tickets,
        private readonly SupportSettings $support,
        private readonly Notifier $notifier,
        private readonly EntityManagerInterface $em,
        #[Autowire('%adzercms.files_dir%/tickets')]
        private readonly string $directory,
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

        $fault = $this->verify($files);
        if (null !== $fault) {
            return $fault;
        }

        $moment = now();
        $ticket = new Ticket($user, mb_substr($subject, 0, self::SUBJECT_MAX), $department, $moment);
        $this->em->persist($ticket);

        $this->store($this->say($ticket, $user, false, $body, $moment), $files);
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

        $fault = $this->verify($files);
        if (null !== $fault) {
            return $fault;
        }

        $moment = now();
        $this->store($this->say($ticket, $author, $fromStaff, $body, $moment), $files);
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

        $fault = $this->verify($files);
        if (null !== $fault) {
            return $fault;
        }

        $stale = [];
        foreach ($message->getAttachments() as $attachment) {
            if (\in_array($attachment->getId(), $dropped, true)) {
                $stale[] = $this->forget($attachment);
            }
        }

        $this->store($message, $files);
        $message->edit($body, now());
        $this->em->flush();
        Attachments::erase($stale);

        return null;
    }

    public function remove(TicketMessage $message): ?Refusal
    {
        if ($message->getTicket()->getStatus()->isClosed()) {
            return new Refusal(t('support.refusal.closed'));
        }

        $stale = [];
        foreach ($message->getAttachments() as $attachment) {
            $stale[] = $this->forget($attachment);
        }

        $message->getTicket()->getMessages()->removeElement($message);
        $this->em->remove($message);
        $this->em->flush();
        Attachments::erase($stale);

        return null;
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

    public function path(TicketAttachment $attachment): string
    {
        return $this->directory.'/'.$attachment->getStoredName();
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

    /** @param array<mixed> $files */
    private function verify(array $files): ?Refusal
    {
        return Attachments::fault($files, $this->support->attachmentLimit(), $this->support->attachmentTypes());
    }

    /** @param array<mixed> $files */
    private function store(TicketMessage $message, array $files): void
    {
        foreach (Attachments::uploads($files) as $file) {
            $kept = Attachments::keep($file, $this->directory);
            $this->em->persist(
                new TicketAttachment($message, $kept['name'], $kept['stored'], $kept['mime'], $kept['size']),
            );
        }
    }

    private function forget(TicketAttachment $attachment): string
    {
        $path = $this->path($attachment);
        $attachment->getMessage()->getAttachments()->removeElement($attachment);
        $this->em->remove($attachment);

        return $path;
    }
}
