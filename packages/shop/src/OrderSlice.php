<?php

declare(strict_types=1);

namespace AdzerCMS\Shop;

use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum OrderSlice: string
{
    case Fresh = 'new';
    case Work = 'progress';
    case Awaiting = 'awaiting';
    case Closed = 'closed';

    /** @param array<string, int> $counts */
    public function label(array $counts): TranslatableInterface
    {
        return t('shop.slice.'.$this->value, ['count' => array_sum(array_map(static fn (OrderStatus $status): int => $counts[$status->value] ?? 0, $this->statuses()))]);
    }

    /** @return non-empty-list<OrderStatus> */
    public function statuses(): array
    {
        return match ($this) {
            self::Fresh => [OrderStatus::New],
            self::Work => [OrderStatus::InProgress],
            self::Awaiting => [OrderStatus::AwaitingPayment],
            self::Closed => [OrderStatus::Done, OrderStatus::Cancelled],
        };
    }
}
