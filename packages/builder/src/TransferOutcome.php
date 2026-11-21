<?php

declare(strict_types=1);

namespace AdzerCMS\Builder;

use AdzerCMS\Core\Entity\User;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum TransferOutcome: string
{
    case Accepted = 'accepted';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): TranslatableInterface
    {
        return match ($this) {
            self::Accepted => t('builder.transfer.accepted'),
            self::Cancelled => t('builder.transfer.cancelled'),
            self::Expired => t('builder.transfer.expired'),
        };
    }

    /** @return list<User> */
    public function notified(User $sender, User $recipient): array
    {
        return match ($this) {
            self::Accepted => [$sender],
            self::Cancelled => [$recipient],
            self::Expired => [$sender, $recipient],
        };
    }
}
