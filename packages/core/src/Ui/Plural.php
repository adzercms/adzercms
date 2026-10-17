<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

final class Plural
{
    public static function of(int $count, string $one, string $few, string $many): string
    {
        return $count.' '.self::word($count, $one, $few, $many);
    }

    public static function word(int $count, string $one, string $few, string $many): string
    {
        $tens = $count % 100;
        $units = $count % 10;

        return match (true) {
            $tens >= 11 && $tens <= 14 => $many,
            1 === $units => $one,
            $units >= 2 && $units <= 4 => $few,
            default => $many,
        };
    }
}
