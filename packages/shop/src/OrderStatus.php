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

    public const MANUAL = [self::New, self::InProgress, self::Done, self::Cancelled];

    public function label(): TranslatableInterface
    {
        return match ($this) {
            self::AwaitingPayment => t('shop.status.awaiting'),
            self::New => t('shop.status.new'),
            self::InProgress => t('shop.status.progress'),
            self::Done => t('core.state.done'),
            self::Cancelled => t('shop.status.cancelled'),
        };
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
