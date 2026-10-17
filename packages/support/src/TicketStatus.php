<?php

declare(strict_types=1);

namespace AdzerCMS\Support;

use AdzerCMS\Contracts\Ui\Badged;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum TicketStatus: string implements Badged
{
    case Open = 'open';
    case WaitingStaff = 'waiting_staff';
    case WaitingUser = 'waiting_user';
    case Closed = 'closed';

    public function label(): TranslatableInterface
    {
        return t('support.ticket_status.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::WaitingStaff => 'warning',
            self::WaitingUser => 'info',
            self::Closed => 'secondary',
        };
    }

    /** @return list<self> */
    public static function active(): array
    {
        return [self::Open, self::WaitingStaff, self::WaitingUser];
    }

    public function isClosed(): bool
    {
        return self::Closed === $this;
    }
}
