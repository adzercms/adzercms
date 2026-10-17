<?php

declare(strict_types=1);

namespace AdzerCMS\Support;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Support\Entity\Ticket;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;

final readonly class Subjects
{
    /** @param iterable<TicketSubject> $subjects */
    public function __construct(#[AutowireIterator(TicketSubject::class)] private iterable $subjects)
    {
    }

    /** @return list<string> */
    public function fields(User $user, Request $request): array
    {
        $fields = [];
        foreach ($this->subjects as $subject) {
            $fields[] = $subject->field($user, $request);
        }

        return array_values(array_filter($fields, static fn (?string $field): bool => null !== $field));
    }

    public function check(User $user, Request $request): ?Refusal
    {
        foreach ($this->subjects as $subject) {
            $refusal = $subject->check($user, $request);
            if (null !== $refusal) {
                return $refusal;
            }
        }

        return null;
    }

    public function attach(Ticket $ticket, Request $request): void
    {
        foreach ($this->subjects as $subject) {
            $subject->attach($ticket, $request);
        }
    }

    /** @return list<string> */
    public function links(Ticket $ticket, User $viewer): array
    {
        $links = [];
        foreach ($this->subjects as $subject) {
            $links[] = $subject->link($ticket, $viewer);
        }

        return array_values(array_filter($links, static fn (?string $link): bool => null !== $link));
    }
}
