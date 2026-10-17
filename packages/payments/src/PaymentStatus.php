<?php

declare(strict_types=1);

namespace AdzerCMS\Payments;

use AdzerCMS\Contracts\Ui\Badged;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum PaymentStatus: string implements Badged
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): TranslatableInterface
    {
        return t('payments.status.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Confirmed => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'secondary',
        };
    }
}
