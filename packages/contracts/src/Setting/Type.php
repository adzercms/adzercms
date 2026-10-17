<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Setting;

enum Type
{
    case Text;
    case Secret;
    case Image;
    case Time;
    case Toggle;
    case Container;
    case Choice;
    case Decimal;
}
