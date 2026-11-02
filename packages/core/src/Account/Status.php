<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Account;

use AdzerCMS\Contracts\Ui\Badged;
use AdzerCMS\Core\Ui\State;
use Symfony\Contracts\Translation\TranslatableInterface;

enum Status: string implements Badged
{
    case Active = 'active';
    case Stopped = 'stopped';
    case Archived = 'archived';

    public function label(): TranslatableInterface
    {
        return $this->state()->label();
    }

    public function badge(): string
    {
        return $this->state()->badge();
    }

    private function state(): State
    {
        return match ($this) {
            self::Active => State::Active,
            self::Stopped => State::Disabled,
            self::Archived => State::Archived,
        };
    }
}
