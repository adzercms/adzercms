<?php

declare(strict_types=1);

namespace AdzerCMS\Leads;

use AdzerCMS\Contracts\Ui\Badged;
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
        return t('leads.group.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Fresh => 'info',
            self::Work => 'warning',
            self::Done => 'success',
            self::Refused => 'danger',
        };
    }

    public function rank(): int
    {
        return (int) array_search($this, self::cases(), true);
    }
}
