<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Setting\Fixture;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Owner;
use AdzerCMS\Contracts\Setting\Rule;
use AdzerCMS\Contracts\Setting\Type;

use function Symfony\Component\Translation\t;

final class Shop implements Owner, Rule
{
    public const TITLE = 'shop.title';
    public const PHONE = 'shop.phone';
    public const TOKEN = 'shop.token';
    public const CERTIFICATE = 'shop.certificate';
    public const DIGITS = 'Shop phone takes digits only';

    public function settingFields(): array
    {
        return [
            new Field(self::TITLE, t('Shop title')),
            new Field(self::PHONE, t('Shop phone')),
            new Field(self::TOKEN, t('Till token'), Type::Secret),
            new Field(self::CERTIFICATE, t('Till certificate'), Type::Container),
        ];
    }

    public function check(Field $field, string $value): ?Refusal
    {
        return self::PHONE === $field->name && !ctype_digit($value) ? new Refusal(t(self::DIGITS)) : null;
    }
}
