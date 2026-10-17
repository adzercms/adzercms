<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Account;

use Twig\Attribute\AsTwigFilter;

final class Hostname
{
    public static function ascii(string $name): string
    {
        $name = self::bare($name);
        $ascii = '' === $name ? false : idn_to_ascii($name, \IDNA_NONTRANSITIONAL_TO_ASCII, \INTL_IDNA_VARIANT_UTS46);

        return false === $ascii ? $name : $ascii;
    }

    #[AsTwigFilter('idn')]
    public static function unicode(string $name): string
    {
        $name = self::bare($name);
        $unicode = '' === $name ? false : idn_to_utf8($name, \IDNA_NONTRANSITIONAL_TO_UNICODE, \INTL_IDNA_VARIANT_UTS46);

        return false === $unicode ? $name : $unicode;
    }

    public static function relative(string $name, string $zone): string
    {
        $name = self::unicode($name);
        $zone = self::unicode($zone);

        if ('' === $name || '@' === $name || $name === $zone) {
            return '';
        }

        return str_ends_with($name, '.'.$zone) ? mb_substr($name, 0, -mb_strlen($zone) - 1) : $name;
    }

    private static function bare(string $name): string
    {
        return mb_strtolower(rtrim(trim($name), '.'));
    }
}
