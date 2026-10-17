<?php

declare(strict_types=1);

namespace AdzerCMS\Support;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Support\Entity\Ticket;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;

#[AutoconfigureTag]
interface TicketSubject
{
    public function field(User $user, Request $request): ?string;

    public function check(User $user, Request $request): ?Refusal;

    public function attach(Ticket $ticket, Request $request): void;

    public function link(Ticket $ticket, User $viewer): ?string;
}
