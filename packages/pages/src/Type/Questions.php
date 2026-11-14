<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Type;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Type as FieldType;
use AdzerCMS\Core\Setting\Values;
use AdzerCMS\Core\Text\Markup;

use function Symfony\Component\Translation\t;

final class Questions extends Plain
{
    private const QUESTION = 'question';
    private const ANSWER = 'answer';

    public function __construct(private readonly Markup $markup)
    {
    }

    public function key(): string
    {
        return 'questions';
    }

    public function fields(): array
    {
        return [self::title(), self::pairs()];
    }

    public function schema(array $values): ?array
    {
        $pairs = Values::entries(self::pairs(), $values[self::pairs()->name] ?? '');

        return [] === $pairs ? null : [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn (array $pair): array => [
                '@type' => 'Question',
                'name' => $pair[self::QUESTION],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(strip_tags($this->markup->html($pair[self::ANSWER])))],
            ], $pairs),
        ];
    }

    private static function pairs(): Field
    {
        return new Field('pairs', t('pages.field.pairs'), FieldType::Rows, fields: [
            new Field(self::QUESTION, t('pages.field.question')),
            new Field(self::ANSWER, t('pages.field.answer'), FieldType::Markup),
        ]);
    }
}
