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
        $valid = self::LEAD === $value || (str_starts_with($value, '/') && !str_starts_with($value, '//')) || str_starts_with($value, 'https://');

        return self::LINK === $field->name && !$valid ? new Refusal(t('pages.refusal.link', ['field' => $field->label])) : null;
    }
}
