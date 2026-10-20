<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatableInterface;

final class Table
{
    public const SIZE = 50;
    public const ROWS = 'rows';

    /** @var array<string, string> */
    private array $values = [];

    private int $total = 0;

    /**
     * @param list<Column>         $columns
     * @param list<Filter>         $filters
     * @param array<string, mixed> $sticky
     */
    private function __construct(
        public readonly array $columns,
        public readonly array $filters,
        public readonly string $route,
        public readonly array $sticky,
        public readonly string $key,
        public readonly TranslatableInterface $empty,
        public readonly bool $ascending,
        public readonly int $page,
        public readonly int $size,
        public readonly bool $live,
        public readonly string $id,
        public readonly ?Order $order,
    ) {
    }

    /**
     * @param list<Column>         $columns
     * @param list<Filter>         $filters
     * @param array<string, mixed> $sticky
     */
    public static function of(
        Request $request,
        string $route,
        array $columns,
        string $key,
        TranslatableInterface $empty,
        bool $ascending = true,
        array $filters = [],
        array $sticky = [],
        int $size = self::SIZE,
        bool $live = false,
        string $id = self::ROWS,
        ?Order $order = null,
    ): self {
        $sortable = [];
        foreach (null === $order ? $columns : [] as $column) {
            if ($live ? null !== $column->value : [] !== $column->fields) {
                $sortable[$column->key] = true;
            }
        }

        $asked = (string) $request->query->get(self::param($id, 'sort'), '');

        if (isset($sortable[$asked])) {
            $key = $asked;
            $ascending = 'asc' === $request->query->get(self::param($id, 'order'), 'desc');
        }

        $table = new self(
            $columns,
            $filters,
            $route,
            array_filter($sticky, static fn (mixed $value): bool => '' !== $value && null !== $value),
            $key,
            $empty,
            null === $order ? $ascending : true,
            max(1, $request->query->getInt(self::param($id, 'page'), 1)),
            $size,
            $live,
            $id,
            $order,
        );
        $table->values = self::values($request, $filters, $id);

        return $table;
    }

    /**
     * @param list<Filter> $filters
     *
     * @return array<string, string>
     */
    public static function values(Request $request, array $filters, string $id = self::ROWS): array
    {
        $values = [];

        foreach ($filters as $filter) {
            $value = trim((string) $request->query->get(self::param($id, $filter->name), ''));
            if ('' !== $value && $filter->accepts($value)) {
                $values[$filter->name] = $value;
            }
        }

        return $values;
    }

    public function term(): string
    {
        return $this->value(Filter::TERM);
    }

    public function value(string $name): string
    {
        return $this->values[$name] ?? '';
    }

    public function field(Filter $filter): string
    {
        return self::param($this->id, $filter->name);
    }

    /** @return array<string, mixed> */
    public function base(): array
    {
        if (!array_any($this->columns, $this->sortable(...))) {
            return $this->sticky;
        }

        return $this->sticky + [
            self::param($this->id, 'sort') => $this->key,
            self::param($this->id, 'order') => $this->ascending ? 'asc' : 'desc',
        ];
    }

    /** @return array<string, mixed> */
    public function params(): array
    {
        return $this->query() + $this->base();
    }

    /** @return array<string, string> */
    private function query(): array
    {
        $query = [];

        foreach ($this->values as $name => $value) {
            $query[self::param($this->id, $name)] = $value;
        }

        return $query;
    }

    /** @return array<string, mixed> */
    public function pageLink(int $number): array
    {
        return [self::param($this->id, 'page') => $number] + $this->params();
    }

    /** @return array<string, mixed> */
    public function link(string $key): array
    {
        return $this->query() + $this->sticky + [
            self::param($this->id, 'sort') => $key,
            self::param($this->id, 'order') => $this->key === $key && $this->ascending ? 'desc' : 'asc',
        ];
    }

    public function mark(string $key): string
    {
        return $this->key === $key ? 'sorted-'.($this->ascending ? 'asc' : 'desc') : '';
    }

    public function aside(string $key): string
    {
        return true === $this->column($key)?->secondary ? 'hide-sm' : '';
    }

    public function sortable(Column $column): bool
    {
        return null === $this->order && ($this->live ? null !== $column->value : [] !== $column->fields);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->size;
    }

    public function total(int $total): self
    {
        $this->total = $total;

        return $this;
    }

    public function pages(): int
    {
        return max(1, (int) ceil($this->total / $this->size));
    }

    public function apply(QueryBuilder $builder): self
    {
        $direction = $this->ascending ? \SortDirection::Ascending : \SortDirection::Descending;

        foreach ($this->fields() as $field) {
            $builder->addOrderBy($field, $direction);
        }

        return $this;
    }

    public function order(string $tail): string
    {
        $direction = $this->ascending ? 'ASC' : 'DESC';
        $parts = [];

        foreach ($this->fields() as $field) {
            $parts[] = $field.' '.$direction;
        }

        $parts[] = $tail;

        return 'ORDER BY '.implode(', ', $parts);
    }

    /**
     * @param list<mixed> $rows
     *
     * @return list<mixed>
     */
    public function rows(array $rows): array
    {
        $term = $this->term();

        if ('' !== $term) {
            $rows = array_filter($rows, fn (mixed $row): bool => $this->matches($row, $term));
        }

        $column = null === $this->order ? $this->column($this->key) : null;

        if (null !== $column?->value) {
            $direction = $this->ascending ? 1 : -1;
            usort($rows, static fn (mixed $left, mixed $right): int => $direction * self::compare(
                ($column->value)($left),
                ($column->value)($right),
            ));
        }

        $this->total = \count($rows);

        return \array_slice(array_values($rows), $this->offset(), $this->size);
    }

    private static function param(string $id, string $name): string
    {
        return self::ROWS === $id ? $name : $id.'_'.$name;
    }

    /** @return list<string> */
    private function fields(): array
    {
        return $this->order->fields ?? $this->column($this->key)->fields ?? [];
    }

    private function column(string $key): ?Column
    {
        foreach ($this->columns as $column) {
            if ($column->key === $key) {
                return $column;
            }
        }

        return null;
    }

    private function matches(mixed $row, string $term): bool
    {
        foreach ($this->columns as $column) {
            if (null !== $column->value && false !== mb_stripos($column->text($row), $term)) {
                return true;
            }
        }

        return false;
    }

    private static function compare(mixed $left, mixed $right): int
    {
        if ($left instanceof \DateTimeInterface || $right instanceof \DateTimeInterface) {
            return self::moment($left) <=> self::moment($right);
        }

        if (is_numeric($left) && is_numeric($right)) {
            return (float) $left <=> (float) $right;
        }

        return strnatcasecmp(Column::scalar($left), Column::scalar($right));
    }

    private static function moment(mixed $value): int
    {
        return $value instanceof \DateTimeInterface ? $value->getTimestamp() : 0;
    }
}
