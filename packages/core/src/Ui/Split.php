<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

final class Split
{
    private const WORDS = '/[\s,]+/';
    private const LINES = '/\s*[\r\n;]+\s*/';
    private const ITEMS = '/\s*,\s*/';

    /** @return list<string> */
    public static function words(?string $value): array
    {
        return self::by(self::WORDS, $value);
    }

    /** @return list<string> */
    public static function items(?string $value): array
    {
        return self::by(self::ITEMS, $value);
    }

    /** @return list<string> */
    public static function lines(?string $value): array
    {
        return self::by(self::LINES, $value);
    }

    /** @return list<string> */
    private static function by(string $pattern, ?string $value): array
    {
        $value = trim((string) $value);

        return '' === $value ? [] : (preg_split($pattern, $value, -1, \PREG_SPLIT_NO_EMPTY) ?: []);
    }
}
