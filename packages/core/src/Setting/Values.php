<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Setting;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Contracts\Ui\Scheme;

use function Symfony\Component\Translation\t;

final class Values
{
    private const TIME = '/^([01]?\d|2[0-3]):([0-5]\d)$/';
    private const COLOR = '/^#[0-9a-f]{6}$/i';
    private const JSON = \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES;
    private const BLANK_ROWS = 2;

    /** @return string|list<array<string, string>> */
    public static function form(Field $field, string $value): string|array
    {
        $picked = Type::Palette === $field->type ? self::picked($field, $value) : null;

        return match (true) {
            Type::Secret === $field->type => '',
            Type::Toggle === $field->type => (Toggle::tryFrom($value) ?? Toggle::Off)->value,
            Type::Rows === $field->type => [
                ...array_values(array_filter(self::entries($field, $value), static fn (array $row): bool => '' !== implode('', $row))),
                ...array_fill(0, self::BLANK_ROWS, array_fill_keys(array_column($field->fields, 'name'), '')),
            ],
            $picked instanceof Scheme => $picked->key,
            null !== $picked => $picked,
            default => $value,
        };
    }

    public static function picked(Field $field, string $value): Scheme|string
    {
        return self::scheme($field, $value) ?? self::color($value) ?? $field->schemes[0] ?? '';
    }

    public static function posted(Field $field, mixed $input): ?string
    {
        return match (true) {
            \is_string($input) => $input,
            Type::Rows === $field->type && \is_array($input) => json_encode(array_values($input), self::JSON),
            default => null,
        };
    }

    public static function check(Field $field, string $value): string|Refusal
    {
        $value = match ($field->type) {
            Type::Lines => self::lined($value),
            Type::Markup => '' === trim($value) ? '' : $value,
            default => trim($value),
        };
        if ('' === $value) {
            return '';
        }

        return match ($field->type) {
            Type::Time => null === ($minutes = self::minutes($value))
                ? new Refusal(t('core.setting.time', ['field' => $field->label]))
                : \sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60),
            Type::Toggle => null === Toggle::tryFrom($value) ? new Refusal(t('core.setting.toggle', ['field' => $field->label])) : $value,
            Type::Choice => isset($field->choices()[$value]) ? $value : new Refusal(t('core.setting.choice', ['field' => $field->label])),
            Type::Color => self::color($value) ?? new Refusal(t('core.setting.color', ['field' => $field->label])),
            Type::Palette => null !== self::scheme($field, $value) ? $value : self::color($value) ?? new Refusal(t('core.setting.choice', ['field' => $field->label])),
            Type::Decimal => is_numeric($value = str_replace(',', '.', $value)) ? $value : new Refusal(t('core.setting.decimal', ['field' => $field->label])),
            Type::Rows => self::rows($field, $value),
            default => $value,
        };
    }

    /** @return list<array<string, string>> */
    public static function entries(Field $field, string $value): array
    {
        try {
            $rows = '' === $value ? [] : json_decode($value, true, 3, self::JSON);
        } catch (\JsonException) {
            return [];
        }

        $entries = [];
        foreach (\is_array($rows) ? $rows : [] as $row) {
            $entry = [];
            foreach ($field->fields as $column) {
                $cell = \is_array($row) ? $row[$column->name] ?? '' : '';
                $entry[$column->name] = \is_string($cell) ? $cell : '';
            }
            $entries[] = $entry;
        }

        return $entries;
    }

    public static function minutes(string $value): ?int
    {
        return 1 === preg_match(self::TIME, $value, $time) ? (int) $time[1] * 60 + (int) $time[2] : null;
    }

    public static function scheme(Field $field, string $key): ?Scheme
    {
        return array_find($field->schemes, static fn (Scheme $scheme): bool => $scheme->key === $key);
    }

    public static function color(string $value): ?string
    {
        return 1 === preg_match(self::COLOR, $value) ? strtolower($value) : null;
    }

    private static function lined(string $value): string
    {
        return implode("\n", array_filter(array_map(trim(...), preg_split('/\R/u', $value) ?: []), static fn (string $line): bool => '' !== $line));
    }

    private static function rows(Field $field, string $value): string|Refusal
    {
        $refusal = new Refusal(t('core.setting.rows', ['field' => $field->label]));
        try {
            $rows = json_decode($value, true, 3, self::JSON);
        } catch (\JsonException) {
            return $refusal;
        }
        if (!\is_array($rows) || !array_is_list($rows)) {
            return $refusal;
        }

        $kept = [];
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                return $refusal;
            }
            $cells = [];
            foreach ($field->fields as $column) {
                $cell = $row[$column->name] ?? '';
                $checked = \is_string($cell) ? self::check($column, $cell) : $refusal;
                if ($checked instanceof Refusal) {
                    return $checked;
                }
                if ('' !== $checked) {
                    $cells[$column->name] = $checked;
                }
            }
            if ([] !== $cells) {
                $kept[] = $cells;
            }
        }

        return [] === $kept ? '' : json_encode($kept, self::JSON);
    }
}
