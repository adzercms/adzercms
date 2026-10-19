<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use AdzerCMS\Contracts\Ui\Titled;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\QueryBuilder;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

final class Filter
{
    public const TERM = 'q';
    public const DAY = '/^\d{4}-\d{2}-\d{2}$/';
    private const INTEGER_MAX = 2147483647;

    /** @param array<array-key, string|TranslatableInterface> $options */
    private function __construct(
        public readonly string $name,
        public readonly ?TranslatableInterface $label,
        public readonly array $options,
        public readonly bool $search,
        public readonly string $selected,
        public readonly bool $hidden,
        public readonly bool $day = false,
    ) {
    }

    public static function search(): self
    {
        return new self(self::TERM, t('core.filter.search'), [], true, '', false);
    }

    /** @param array<array-key, string|TranslatableInterface> $options */
    public static function select(string $name, TranslatableInterface $any, array $options): self
    {
        return new self($name, $any, $options, false, '', false);
    }

    /** @param array<array-key, string|TranslatableInterface> $options */
    public static function choice(string $name, array $options, string $selected, bool $hidden = false): self
    {
        return new self($name, null, $options, false, $selected, $hidden);
    }

    public static function date(string $name, string $selected, bool $hidden = false): self
    {
        return new self($name, null, [], false, $selected, $hidden, true);
    }

    /** @param list<Titled&\BackedEnum> $cases */
    public static function cases(string $name, TranslatableInterface $any, array $cases): self
    {
        $options = [];

        foreach ($cases as $case) {
            $options[(string) $case->value] = $case->label();
        }

        return self::select($name, $any, $options);
    }

    public function required(): bool
    {
        return '' !== $this->selected;
    }

    public function accepts(string $value): bool
    {
        return $this->day ? 1 === preg_match(self::DAY, $value) : $this->search || isset($this->options[$value]);
    }

    public function pick(string $value): string
    {
        return '' !== $value && $this->accepts($value) ? $value : $this->selected;
    }

    /** @param list<string> $fields */
    public static function matching(QueryBuilder $builder, string $term, array $fields): Orx
    {
        $builder->setParameter('term', self::pattern($term));

        return $builder->expr()->orX(...array_map(self::like(...), $fields));
    }

    /**
     * @param list<string> $fields
     *
     * @return array{string, array{term: string}}
     */
    public static function matchingSql(string $term, array $fields): array
    {
        return ['('.implode(' OR ', array_map(self::like(...), $fields)).')', ['term' => self::pattern($term)]];
    }

    private static function pattern(string $term): string
    {
        return '%'.addcslashes(mb_strtolower(trim($term)), '%_\\').'%';
    }

    private static function like(string $field): string
    {
        return \sprintf('LOWER(%s) LIKE :term', $field);
    }

    public static function number(string $term): ?int
    {
        $term = trim($term);

        return ctype_digit($term) && (int) $term <= self::INTEGER_MAX ? (int) $term : null;
    }
}
