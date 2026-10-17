<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use Symfony\Component\Clock\DatePoint;
use Twig\Attribute\AsTwigFunction;

use function Symfony\Component\Clock\now;

final class Clock
{
    #[AsTwigFunction('now')]
    public static function now(): DatePoint
    {
        return now();
    }
}
