<?php

declare(strict_types=1);

namespace AdzerCMS\Shop;

use AdzerCMS\Contracts\Ui\Badged;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum OrderStatus: string implements Badged
{
    case AwaitingPayment = 'awaiting';
    case New = 'new';
    case InProgress = 'progress';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): TranslatableInterface
    {
        return t('shop.status.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'warning',
            self::New => 'info',
            self::InProgress => 'secondary',
            self::Done => 'success',
            self::Cancelled => 'danger',
        };
    }
}
