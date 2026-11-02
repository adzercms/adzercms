<?php

declare(strict_types=1);

namespace AdzerCMS\Leads;

use AdzerCMS\Contracts\Ui\Badged;
use AdzerCMS\Core\Ui\State;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum StageGroup: string implements Badged
{
    case Fresh = 'new';
    case Work = 'work';
    case Done = 'done';
    case Refused = 'refused';

    public function label(): TranslatableInterface
    {
        return self::Done === $this ? State::Done->label() : t('leads.group.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Fresh => 'info',
            self::Work => 'warning',
            self::Done => State::Done->badge(),
            self::Refused => 'danger',
        };
    }

    public function slice(): Slice
    {
        return match ($this) {
            self::Fresh => Slice::Fresh,
            self::Work => Slice::Work,
            self::Done, self::Refused => Slice::Closed,
        };
    }

    public function rank(): int
    {
        return (int) array_search($this, self::cases(), true);
    }
}
