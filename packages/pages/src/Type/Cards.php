<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Type;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Type as FieldType;
use AdzerCMS\Core\Setting\Values;

use function Symfony\Component\Translation\t;

final class Cards extends Plain
{
    public function key(): string
    {
        return 'cards';
    }

    public function fields(): array
    {
        return [self::title(), self::cards()];
    }

    public function check(Field $field, string $value): ?Refusal
    {
        if (self::cards()->name !== $field->name) {
            return parent::check($field, $value);
        }
        $link = new Field(Button::LINK, t('pages.field.link'));
        foreach (Values::entries($field, $value) as $card) {
            if ('' !== $card[Button::LINK] && null !== ($refusal = Button::check($link, $card[Button::LINK]))) {
                return $refusal;
            }
        }

        return null;
    }

    private static function cards(): Field
    {
        return new Field('cards', t('pages.field.cards'), FieldType::Rows, fields: [
            new Field('picture', t('pages.field.picture'), FieldType::Photo),
            self::title(),
            self::text(),
            new Field(Button::LINK, t('pages.field.link')),
        ]);
    }
}
