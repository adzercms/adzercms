<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use AdzerCMS\Contracts\Ui\Cell;
use AdzerCMS\Contracts\Ui\Column as Declared;
use AdzerCMS\Contracts\Ui\Columns;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class ModuleColumns
{
    private const ALIAS = 'module_order_';

    /** @param iterable<Columns> $columns */
    public function __construct(
        #[AutowireIterator(Columns::class)]
        private iterable $columns,
    ) {
    }

    /** @return list<Column> */
    public function columns(string $listing): array
    {
        return self::declare($this->declared($listing));
    }

    public function order(string $listing, Table $table, QueryBuilder $builder, string $subject): void
    {
        self::sort($this->declared($listing), $table, $builder, $subject);
    }

    /**
     * @param list<Declared> $declared
     *
     * @return list<Column>
     */
    public static function declare(array $declared): array
    {
        $columns = [];
        foreach ($declared as $column) {
            $listed = Column::of($column->key, $column->label, self::alias($column->key));
            $columns[] = $column->secondary ? $listed->aside() : $listed;
        }

        return $columns;
    }

    /** @param list<Declared> $declared */
    public static function sort(array $declared, Table $table, QueryBuilder $builder, string $subject): void
    {
        foreach ($declared as $column) {
            if ($column->key === $table->key) {
                $builder->addSelect(\sprintf('(%s) AS HIDDEN %s', ($column->order)($subject), self::alias($column->key)));
            }
        }
    }

    /** @return list<int> */
    public function search(string $listing, string $term): array
    {
        $found = [];
        foreach ('' === $term ? [] : $this->modules($listing) as $module) {
            $found = [...$found, ...$module->search($term)];
        }

        return $found;
    }

    /**
     * @param list<int> $subjects
     *
     * @return array<int, array<string, Cell>>
     */
    public function cells(string $listing, array $subjects): array
    {
        $cells = [];
        foreach ($this->modules($listing) as $module) {
            foreach ($module->cells($subjects) as $id => $values) {
                $cells[$id] = ($cells[$id] ?? []) + $values;
            }
        }

        return $cells;
    }

    /** @return list<Declared> */
    private function declared(string $listing): array
    {
        $declared = [];
        foreach ($this->modules($listing) as $module) {
            $declared = [...$declared, ...$module->columns()];
        }

        return $declared;
    }

    /** @return list<Columns> */
    private function modules(string $listing): array
    {
        return array_values(array_filter([...$this->columns], static fn (Columns $columns): bool => $listing === $columns->listing()));
    }

    private static function alias(string $key): string
    {
        return self::ALIAS.preg_replace('/\W/', '_', $key);
    }
}
