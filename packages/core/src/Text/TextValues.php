<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Text;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Rule;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Setting\Values;
use AdzerCMS\Core\Showcase\Sections;
use Symfony\Contracts\Service\ResetInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Twig\Attribute\AsTwigFunction;

use function Symfony\Component\Translation\t;

final class TextValues implements Rule, ResetInterface
{
    private const PATTERN = '/(?<code>^ {0,3}(?<fence>`{3,}|~{3,}).*?^ {0,3}\k<fence>|(?<tick>`+).+?\k<tick>)|\{\{\s*(?<section>\w+)\.(?<key>\w+)\s*\}\}/msu';
    private const SEPARATOR = ', ';

    /** @var array<string, array<string, string>> */
    private array $taken = [];

    public function __construct(private readonly Sections $sections)
    {
    }

    public function fill(string $text): string
    {
        return preg_replace_callback(
            self::PATTERN,
            fn (array $found): string => (null === $found['code'] ? $this->value((string) $found['section'], (string) $found['key']) : null) ?? (string) $found[0],
            $text,
            flags: \PREG_UNMATCHED_AS_NULL,
        ) ?? $text;
    }

    public function refusal(string $text, TranslatableInterface $field): ?Refusal
    {
        preg_match_all(self::PATTERN, $text, $found, \PREG_SET_ORDER | \PREG_UNMATCHED_AS_NULL);
        $unknown = [];
        foreach ($found as $match) {
            if (null === $match['code'] && null === $this->value((string) $match['section'], (string) $match['key'])) {
                $unknown[$match[0]] = $match[0];
            }
        }

        return [] === $unknown ? null : new Refusal(t('core.text_values.unknown', ['field' => $field, 'values' => implode(', ', $unknown)]));
    }

    public function check(Field $field, string $value): ?Refusal
    {
        if (Type::Markup === $field->type) {
            return $this->refusal($value, $field->label);
        }
        if (Type::Rows !== $field->type) {
            return null;
        }
        foreach (Values::entries($field, $value) as $row) {
            foreach ($field->fields as $column) {
                $refusal = Type::Markup === $column->type ? $this->refusal($row[$column->name], $column->label) : null;
                if (null !== $refusal) {
                    return $refusal;
                }
            }
        }

        return null;
    }

    /** @return array<string, string> */
    #[AsTwigFunction('text_values')]
    public function all(): array
    {
        $all = [];
        foreach ($this->sections->keys() as $section) {
            foreach ($this->section($section) as $key => $value) {
                $all[$section.'.'.$key] = $value;
            }
        }

        return $all;
    }

    public function reset(): void
    {
        $this->taken = [];
    }

    private function value(string $section, string $key): ?string
    {
        return $this->section($section)[$key] ?? null;
    }

    /** @return array<string, string> */
    private function section(string $section): array
    {
        if (!isset($this->taken[$section])) {
            $this->taken[$section] = [];
            foreach ($this->sections->section($section) as $key => $value) {
                $text = self::text($value);
                if (\is_string($key) && null !== $text) {
                    $this->taken[$section][$key] = $text;
                }
            }
        }

        return $this->taken[$section];
    }

    private static function text(mixed $value): ?string
    {
        if (!\is_array($value)) {
            return self::scalar($value);
        }

        $items = array_map(self::scalar(...), $value);

        return array_is_list($value) && !\in_array(null, $items, true) ? implode(self::SEPARATOR, $items) : null;
    }

    private static function scalar(mixed $value): ?string
    {
        return \is_string($value) || \is_int($value) || \is_float($value) ? (string) $value : null;
    }
}
