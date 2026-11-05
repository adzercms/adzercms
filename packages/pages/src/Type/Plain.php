<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Type;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Type as FieldType;
use AdzerCMS\Pages\Block;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

abstract class Plain implements Block
{
    public function label(): TranslatableInterface
    {
        return t('pages.block.'.$this->key());
    }

    public function template(): string
    {
        return '@Pages/blocks/'.$this->key().'.html.twig';
    }

    public function data(array $values): array
    {
        return [];
    }

    public function schema(array $values): ?array
    {
        return null;
    }

    public function sources(): array
    {
        return [];
    }

    public function check(Field $field, string $value): ?Refusal
    {
        return Button::check($field, $value);
    }

    protected static function title(): Field
    {
        return new Field(self::TITLE, t('pages.field.title'));
    }

    protected static function text(): Field
    {
        return new Field('text', t('pages.field.text'), FieldType::Lines);
    }
}
