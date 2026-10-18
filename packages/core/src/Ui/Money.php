<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

final class Money
{
    public const SCALE = 2;

    public static function add(string $left, string $right): string
    {
        return bcadd(self::numeric($left), self::numeric($right), self::SCALE);
    }

    public static function sub(string $left, string $right): string
    {
        return bcsub(self::numeric($left), self::numeric($right), self::SCALE);
    }

    public static function compare(string $left, string $right): int
    {
        return bccomp(self::numeric($left), self::numeric($right), self::SCALE);
    }

    public static function least(string $left, string $right): string
    {
        return 1 === self::compare($left, $right) ? $right : $left;
    }

    public static function greatest(string $left, string $right): string
    {
        return -1 === self::compare($left, $right) ? $right : $left;
    }

    #[AsTwigFunction('zero')]
    public static function isZero(string $value): bool
    {
        return 0 === self::compare($value, '0');
    }

    public static function isPositive(string $value): bool
    {
        return 1 === self::compare($value, '0');
    }

    public static function divide(string $amount, int $parts): string
    {
        return bcdiv(self::numeric($amount), (string) $parts, self::SCALE);
    }

    public static function divideToWhole(string $amount, string $divisor): int
    {
        if (0 === self::compare($divisor, '0')) {
            return 0;
        }

        return (int) bcdiv(self::numeric($amount), self::numeric($divisor), 0);
    }

    public static function multiply(string $value, int $times): string
    {
        return bcmul(self::numeric($value), (string) $times, self::SCALE);
    }

    public static function percent(string $value, string $percent): string
    {
        return self::normalize(bcdiv(bcmul(self::numeric($value), self::numeric($percent), self::SCALE + 2), '100', self::SCALE));
    }

    public static function withoutPercent(string $value, int $percent): string
    {
        return self::normalize(bcdiv(bcmul(self::numeric($value), '100', self::SCALE + 2), (string) (100 + $percent), self::SCALE));
    }

    public static function normalize(string $value): string
    {
        return bcadd(self::numeric($value), '0', self::SCALE);
    }

    public static function parse(?string $value): ?string
    {
        $value = str_replace([' ', "\u{a0}", ','], ['', '', '.'], trim((string) $value));

        return is_numeric($value) ? self::normalize($value) : null;
    }

    #[AsTwigFilter('plain')]
    public static function plain(string $value): string
    {
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }

    #[AsTwigFilter('money')]
    public static function rubles(string|float|int $value): string
    {
        return number_format((float) $value, 0, ',', "\u{a0}");
    }

    #[AsTwigFilter('exact')]
    public static function exact(string|float|int $value): string
    {
        return number_format((float) $value, self::SCALE, ',', "\u{a0}");
    }

    #[AsTwigFilter('precise')]
    public static function precise(string|float|int $value): string
    {
        $value = self::numeric(self::normalize((string) $value));

        return self::isZero(bcsub($value, bcadd($value, '0', 0), self::SCALE)) ? self::rubles($value) : self::exact($value);
    }

    public static function wholeUp(string $value): string
    {
        $whole = bcadd(self::numeric($value), '0', 0);

        return self::normalize(0 === self::compare($value, $whole) ? $whole : bcadd($whole, '1', 0));
    }

    public static function upTo(string $value, int $step): string
    {
        $whole = self::numeric(self::wholeUp($value));
        $rest = bcmod($whole, (string) $step, 0);

        return self::normalize(0 === bccomp($rest, '0', 0) ? $whole : bcadd(bcsub($whole, $rest, 0), (string) $step, 0));
    }

    public static function fromFloat(float $value): string
    {
        return number_format($value, self::SCALE, '.', '');
    }

    public static function toMinorUnits(string $value): int
    {
        return (int) bcmul(self::numeric($value), '100', 0);
    }

    public static function fromMinorUnits(int $value): string
    {
        return bcdiv((string) $value, '100', self::SCALE);
    }

    /** @return numeric-string */
    private static function numeric(string $value): string
    {
        return is_numeric($value)
            ? $value
            : throw new \InvalidArgumentException(sprintf('%s is not a money amount', $value));
    }
}
