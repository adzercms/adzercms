<?php

declare(strict_types=1);

namespace AdzerCMS\Leads;

use AdzerCMS\Core\Entity\User;
use AdzerCMS\Leads\Entity\Lead;
use AdzerCMS\Leads\Entity\LeadEntry;
use AdzerCMS\Realtime\LiveEntities;

final readonly class LiveLeads implements LiveEntities
{
    public function fields(): array
    {
        return [Lead::class => [], LeadEntry::class => []];
    }

    public function owner(object $entity): ?User
    {
        return null;
    }

    public function staff(): bool
    {
        return true;
    }
}
