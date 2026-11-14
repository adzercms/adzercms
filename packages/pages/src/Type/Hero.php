<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Type;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Type as FieldType;
use AdzerCMS\Contracts\Ui\Fragment;

use function Symfony\Component\Translation\t;

final class Hero extends Call
{
    public function key(): string
    {
        return 'hero';
    }

    public function fields(): array
    {
        return [self::title(), self::text(), new Field('picture', t('pages.field.picture'), FieldType::Photo), ...Button::fields()];
    }

    public function data(array $values): array
    {
        return ['place' => Fragment::SITE_HERO];
    }
}
