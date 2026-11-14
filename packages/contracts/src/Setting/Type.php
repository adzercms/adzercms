<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Setting;

enum Type
{
    case Text;
    case Url;
    case Lines;
    case Markup;
    case Rows;
    case Secret;
    case Image;
    case Photo;
    case Time;
    case Date;
    case Toggle;
    case Container;
    case Choice;
    case Decimal;
    case Color;
    case Palette;

    public function picture(): bool
    {
        return self::Image === $this || self::Photo === $this;
    }

    public function cell(): bool
    {
        return match ($this) {
            self::Text, self::Url, self::Lines, self::Markup, self::Time, self::Date, self::Toggle, self::Choice, self::Decimal, self::Color => true,
            default => false,
        };
    }
}
