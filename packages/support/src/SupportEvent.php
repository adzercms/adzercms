<?php

declare(strict_types=1);

namespace AdzerCMS\Support;

use AdzerCMS\Contracts\Notice\Event;

enum SupportEvent: string implements Event
{
    case TicketReply = 'support.event.ticket_reply';
    case TicketClosed = 'support.event.ticket_closed';

    public function key(): string
    {
        return $this->value;
    }

    public function template(): string
    {
        return match ($this) {
            self::TicketReply => '@Support/notice/ticket_reply',
            self::TicketClosed => '@Support/notice/ticket_closed',
        };
    }

    public function awaited(): bool
    {
        return false;
    }
}
