<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Tests\Fixture;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Support\Entity\Ticket;
use AdzerCMS\Support\TicketSubject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatableMessage;

final class SubjectFixture implements TicketSubject
{
    public const FIELD = 'item';
    public const OWN = 'own';

    public bool $offered = false;

    /** @var array<int, string> */
    public array $attached = [];

    public function field(User $user, Request $request): ?string
    {
        return $this->offered
            ? \sprintf('<label>Item <input name="%s" value="%s"></label>', self::FIELD, htmlspecialchars($request->request->getString(self::FIELD), \ENT_QUOTES))
            : null;
    }

    public function check(User $user, Request $request): ?Refusal
    {
        return $this->offered && self::OWN !== $request->request->getString(self::FIELD)
            ? new Refusal(new TranslatableMessage('Not your item'))
            : null;
    }

    public function attach(Ticket $ticket, Request $request): void
    {
        if ($this->offered) {
            $this->attached[(int) $ticket->getId()] = $request->request->getString(self::FIELD);
        }
    }

    public function link(Ticket $ticket, User $viewer): ?string
    {
        $item = $this->attached[(int) $ticket->getId()] ?? null;

        return null === $item ? null : \sprintf('<a class="subject" href="/items/%1$s/%2$d">%1$s</a>', $item, $viewer->getId());
    }
}
