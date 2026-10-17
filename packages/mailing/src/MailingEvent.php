<?php

declare(strict_types=1);

namespace AdzerCMS\Mailing;

use AdzerCMS\Contracts\Notice\Event;

enum MailingEvent: string implements Event
{
    case Broadcast = 'mailing.event.broadcast';

    public function key(): string
    {
        return $this->value;
    }

    public function template(): string
    {
        return '@Mailing/notice/broadcast';
    }

    public function awaited(): bool
    {
        return false;
    }
}
