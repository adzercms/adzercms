<?php

declare(strict_types=1);

namespace AdzerCMS\Leads;

use AdzerCMS\Contracts\Notice\Event;

enum LeadsEvent: string implements Event
{
    case NewLead = 'leads.event.new_lead';

    public function key(): string
    {
        return $this->value;
    }

    public function template(): string
    {
        return match ($this) {
            self::NewLead => '@Leads/notice/new_lead',
        };
    }

    public function awaited(): bool
    {
        return false;
    }
}
