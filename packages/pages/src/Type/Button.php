<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Type;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Setting\Field;

use function Symfony\Component\Translation\t;

final class Button
{
    public const LABEL = 'button';
    public const LINK = 'link';
    public const LEAD = '#lead';

    /** @return list<Field> */
    public static function fields(): array
    {
        return [new Field(self::LABEL, t('pages.field.button')), new Field(self::LINK, t('pages.field.link'))];
    }

    public static function check(Field $field, string $value): ?Refusal
    {
        return self::LINK === $field->name && !self::valid($value) ? new Refusal(t('pages.refusal.link', ['field' => $field->label])) : null;
    }

    public static function valid(string $link): bool
    {
        return self::LEAD === $link || (str_starts_with($link, '/') && !str_starts_with($link, '//')) || self::external($link);
    }

    public static function external(string $link): bool
    {
        return str_starts_with($link, 'https://');
    }
}
