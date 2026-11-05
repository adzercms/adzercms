<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Type;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Type as FieldType;

use function Symfony\Component\Translation\t;

final class Advantages extends Plain
{
    public function key(): string
    {
        return 'advantages';
    }

    public function fields(): array
    {
        return [self::title(), new Field('points', t('pages.field.points'), FieldType::Rows, fields: [self::title(), self::text()])];
    }
}
