<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Type;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Type as FieldType;

use function Symfony\Component\Translation\t;

final class Text extends Plain
{
    public function key(): string
    {
        return 'text';
    }

    public function fields(): array
    {
        return [self::title(), new Field('text', t('pages.field.text'), FieldType::Markup)];
    }
}
