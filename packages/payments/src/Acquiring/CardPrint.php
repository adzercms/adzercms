<?php

declare(strict_types=1);

namespace AdzerCMS\Payments\Acquiring;

final class CardPrint
{
    public static function of(string $first, string $last, string $month, string $year): ?string
    {
        return 1 === preg_match('/^\d{6}$/', $first) && 1 === preg_match('/^\d{4}$/', $last)
            && 1 === preg_match('/^(0[1-9]|1[0-2])$/', $month) && 1 === preg_match('/^\d{4}$/', $year)
            ? hash('sha256', $first.$last.$month.$year)
            : null;
    }
}
