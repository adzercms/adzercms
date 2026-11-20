<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Style;

enum Kind: string
{
    case Color = 'color';
    case Length = 'length';
    case Choice = 'choice';
    case Fraction = 'fraction';
}
