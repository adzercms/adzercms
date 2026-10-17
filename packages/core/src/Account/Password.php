<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Account;

use AdzerCMS\Contracts\Refusal;

use function Symfony\Component\Translation\t;

final class Password
{
    public const MIN_LENGTH = 8;

    private const ALPHABET = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const ISSUED = 12;

    public static function issue(): string
    {
        $password = '';
        for ($at = 0; $at < self::ISSUED; ++$at) {
            $password .= self::ALPHABET[random_int(0, \strlen(self::ALPHABET) - 1)];
        }

        return $password;
    }

    public static function refusal(string $password): ?Refusal
    {
        return mb_strlen($password) < self::MIN_LENGTH
            ? new Refusal(t('core.password.short', ['min' => self::MIN_LENGTH]))
            : null;
    }
}
