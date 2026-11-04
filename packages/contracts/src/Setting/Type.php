<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Setting;

enum Type
{
    case Text;
    case Lines;
    case Secret;
    case Image;
    case Time;
    case Toggle;
    case Container;
    case Choice;
    case Decimal;
    case Color;
    case Palette;
}
