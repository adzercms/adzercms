<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Account;

use AdzerCMS\Contracts\Ui\Badged;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum Status: string implements Badged
{
    case Active = 'active';
    case Stopped = 'stopped';
    case Archived = 'archived';

    public function label(): TranslatableInterface
    {
        return t('core.status.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Stopped => 'warning',
            self::Archived => 'secondary',
        };
    }
}
