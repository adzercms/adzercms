<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use AdzerCMS\Contracts\Ui\Badged;
use Symfony\Contracts\Translation\TranslatableInterface;

enum Activity: string implements Badged
{
    case Active = '1';
    case Disabled = '0';

    public static function of(bool $active): self
    {
        return $active ? self::Active : self::Disabled;
    }

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
        return self::Active === $this ? State::Active : State::Disabled;
    }
}
