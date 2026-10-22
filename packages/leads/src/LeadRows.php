<?php

declare(strict_types=1);

namespace AdzerCMS\Leads;

use AdzerCMS\Contracts\Ui\Columns;
use AdzerCMS\Core\Account\LoginNames;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Core\Ui\Table;
use AdzerCMS\Leads\Entity\Lead;
use AdzerCMS\Leads\Entity\Stage;
use AdzerCMS\Leads\Repository\LeadRepository;
use AdzerCMS\Leads\Repository\StageRepository;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;

use function Symfony\Component\Translation\t;

final readonly class LeadRows
{
    public const SLICE = 'slice';
    public const TOPIC = 'topic';
    public const MANAGER = 'manager';
    public const STAGE = 'stage';

    /** @param iterable<Columns> $columns */
    public function __construct(
        private LeadRepository $leads,
        private StageRepository $stages,
        private Handling $handling,
        private LoginNames $logins,
        #[AutowireIterator(Columns::class)]
        private iterable $columns,
    ) {
    }

    /**
     * @param non-empty-list<Slice> $slices
     * @param array<string, mixed>  $sticky
     *
     * @return array<string, mixed>
     */
    public function listing(Request $request, string $route, array $slices, array $sticky = []): array
    {
        $first = $slices[0];
        $labels = [];
        foreach (\array_slice($slices, 1) as $slice) {
            $labels[$slice->value] = $slice->label($this->leads->total($slice));
        }
        $tabs = Filter::tabs(self::SLICE, $first->label($this->leads->total($first)), $labels);
        $slice = Slice::tryFrom(Table::values($request, [$tabs])[self::SLICE] ?? '') ?? $first;

        $stages = [];
        foreach (array_filter($this->stages->funnel(), static fn (Stage $stage): bool => \in_array($stage->getGroup(), $slice->groups(), true)) as $stage) {
            $stages[(string) $stage->getId()] = $stage->label();
        }
        $managers = $this->handling->managers();
        $topics = $this->leads->topics();

        $modules = array_values(array_filter([...$this->columns], static fn (Columns $columns): bool => LeadSubject::LISTING === $columns->listing()));
        $extra = [];
        foreach ($modules as $module) {
            foreach ($module->columns() as $column) {
                $extra[] = $column->secondary ? Column::plain($column->key, $column->label)->aside() : Column::plain($column->key, $column->label);
            }
        }

        $table = Table::of($request, $route, [
            Column::of('number', t('leads.column.number'), 'l.id'),
            Column::plain('topic', t('leads.field.topic')),
            Column::plain('contacts', t('leads.column.contacts')),
            ...$extra,
            Column::of('amount', t('leads.field.amount'), 'l.amount')->aside(),
            Column::plain('manager', t('leads.field.manager'))->aside(),
            Column::of('due', t('leads.field.due'), 'l.due'),
            Column::of('arrived', t('leads.field.arrived'), 'l.arrived')->aside(),
            Column::plain('stage', t('leads.field.stage')),
        ], 'arrived', t('leads.empty'), false, filters: array_values(array_filter([
            $tabs,
            Filter::search(),
            [] === $topics ? null : Filter::select(self::TOPIC, t('leads.filter.topic'), array_combine($topics, $topics)),
            [] === $managers ? null : Filter::select(self::MANAGER, t('leads.filter.manager'), $managers),
            \count($stages) < 2 ? null : Filter::select(self::STAGE, t('leads.filter.stage'), $stages),
        ])), sticky: $sticky);

        $term = trim($table->term());
        $found = [];
        if ('' !== $term) {
            foreach ($modules as $module) {
                $found = [...$found, ...$module->search($term)];
            }
        }

        $builder = $this->leads->listing(
            $slice,
            $term,
            $found,
            $table->value(self::TOPIC),
            '' === $table->value(self::MANAGER) ? null : (int) $table->value(self::MANAGER),
            '' === $table->value(self::STAGE) ? null : (int) $table->value(self::STAGE),
        );
        $total = (int) (clone $builder)->select('COUNT(l.id)')->getQuery()->getSingleScalarResult();
        $table->apply($builder)->total($total);

        /** @var list<Lead> $rows */
        $rows = $builder
            ->addSelect('s', 'm')
            ->addOrderBy('l.id', \SortDirection::Descending)
            ->setMaxResults($table->size)
            ->setFirstResult($table->offset())
            ->getQuery()
            ->getResult();

        $ids = array_map(static fn (Lead $lead): int => (int) $lead->getId(), $rows);
        $cells = [];
        foreach ($modules as $module) {
            foreach ($module->cells($ids) as $id => $values) {
                $cells[$id] = ($cells[$id] ?? []) + $values;
            }
        }
        $assigned = [];
        foreach ($rows as $row) {
            $manager = $row->getManager();
            if ($manager instanceof User) {
                $assigned[(int) $manager->getId()] = $manager;
            }
        }

        return [
            'table' => $table,
            'rows' => $rows,
            'extra' => $extra,
            'cells' => $cells,
            'managers' => $this->logins->ofMany(array_values($assigned)),
        ];
    }
}
