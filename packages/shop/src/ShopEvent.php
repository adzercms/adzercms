<?php

declare(strict_types=1);

namespace AdzerCMS\Shop;

use AdzerCMS\Contracts\Notice\Event;

enum ShopEvent: string implements Event
{
    case NewOrder = 'shop.event.new_order';

    public function key(): string
    {
        return $this->value;
    }

    public function template(): string
    {
        return match ($this) {
            self::NewOrder => '@Shop/notice/new_order',
        };
    }

    public function awaited(): bool
    {
        return false;
    }
}
