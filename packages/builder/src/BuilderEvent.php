<?php

declare(strict_types=1);

namespace AdzerCMS\Builder;

use AdzerCMS\Contracts\Notice\Event;

enum BuilderEvent: string implements Event
{
    case Offer = 'builder.event.offer';
    case Outcome = 'builder.event.outcome';
    case Decision = 'builder.event.decision';

    public function key(): string
    {
        return $this->value;
    }

    public function template(): string
    {
        return match ($this) {
            self::Offer => '@Builder/notice/offer',
            self::Outcome => '@Builder/notice/outcome',
            self::Decision => '@Builder/notice/decision',
        };
    }

    public function awaited(): bool
    {
        return false;
    }
}
