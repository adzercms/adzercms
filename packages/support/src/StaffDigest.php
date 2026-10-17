<?php

declare(strict_types=1);

namespace AdzerCMS\Support;

use AdzerCMS\Support\Entity\Ticket;
use AdzerCMS\Support\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Clock\now;

final readonly class StaffDigest
{
    public const SHOWN = 20;

    private const HOUR = 3600;

    /** @param iterable<DigestSection> $sections */
    public function __construct(
        private TicketRepository $tickets,
        private SupportSettings $support,
        private TranslatorInterface $translator,
        private EntityManagerInterface $em,
        #[AutowireIterator(DigestSection::class)]
        private iterable $sections,
    ) {
    }

    /** @return array{summary: string, lines: list<string>, ids: list<int>} */
    public function collect(bool $fresh, ?\DateTimeImmutable $moment = null): array
    {
        $now = $moment ?? now();
        $deadline = $now->modify(sprintf('-%d hours', $this->support->overdueHours()));
        $tickets = $this->tickets->awaitingStaff($fresh);
        $summary = [];
        $lines = [];
        $ids = [];

        if ([] !== $tickets) {
            $summary[] = $this->translator->trans('support.digest.tickets', ['count' => \count($tickets)]);
            $lines = $this->shown(array_map(
                fn (Ticket $ticket): string => $fresh ? $this->fresh($ticket) : $this->waiting($ticket, $now, $deadline),
                $tickets,
            ));
        }

        foreach ($fresh ? [] : $this->sections as $section) {
            $found = $section->lines();
            if ([] !== $found) {
                $summary[] = $section->summary(\count($found))->trans($this->translator);
                $lines = array_merge($lines, $this->shown($found));
            }
        }

        foreach ($tickets as $ticket) {
            $ids[] = (int) $ticket->getId();
        }

        return ['summary' => implode('; ', $summary), 'lines' => $lines, 'ids' => $ids];
    }

    /** @param list<int> $ids */
    public function mark(array $ids): int
    {
        $moment = now();
        $marked = 0;

        foreach ($this->tickets->byIds($ids) as $ticket) {
            if (null === $ticket->getStaffNotifiedAt()) {
                $ticket->staffNotified($moment);
                ++$marked;
            }
        }

        $this->em->flush();

        return $marked;
    }

    /**
     * @param list<string> $lines
     *
     * @return list<string>
     */
    private function shown(array $lines): array
    {
        $shown = \array_slice($lines, 0, self::SHOWN);

        if (\count($lines) > self::SHOWN) {
            $shown[] = $this->translator->trans('support.digest.more', ['count' => \count($lines) - self::SHOWN]);
        }

        return $shown;
    }

    private function fresh(Ticket $ticket): string
    {
        return $this->translator->trans('support.digest.fresh', [
            'number' => $ticket->getId(),
            'user' => $ticket->getUser()->getId(),
            'department' => $ticket->getDepartment(),
            'subject' => $ticket->getSubject(),
        ]);
    }

    private function waiting(Ticket $ticket, \DateTimeImmutable $now, \DateTimeImmutable $deadline): string
    {
        return $this->translator->trans('support.digest.waiting', [
            'number' => $ticket->getId(),
            'user' => $ticket->getUser()->getId(),
            'hours' => intdiv(max(0, $now->getTimestamp() - $ticket->getLastMessageAt()->getTimestamp()), self::HOUR),
            'subject' => $ticket->getSubject(),
            'overdue' => $ticket->getLastMessageAt() < $deadline ? 'yes' : 'no',
        ]);
    }
}
