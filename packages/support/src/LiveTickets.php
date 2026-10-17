<?php

declare(strict_types=1);

namespace AdzerCMS\Support;

use AdzerCMS\Core\Entity\User;
use AdzerCMS\Realtime\LiveEntities;
use AdzerCMS\Support\Entity\Ticket;
use AdzerCMS\Support\Entity\TicketMessage;

final readonly class LiveTickets implements LiveEntities
{
    public function fields(): array
    {
        return [Ticket::class => ['status', 'lastMessageAt'], TicketMessage::class => []];
    }

    public function owner(object $entity): User
    {
        return match (true) {
            $entity instanceof Ticket => $entity->getUser(),
            $entity instanceof TicketMessage => $entity->getTicket()->getUser(),
            default => throw new \LogicException(sprintf('Not a ticket entity: %s', $entity::class)),
        };
    }

    public function staff(): bool
    {
        return true;
    }
}
