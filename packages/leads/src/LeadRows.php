<?php

declare(strict_types=1);

namespace AdzerCMS\Leads;

use AdzerCMS\Core\Account\LoginNames;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Core\Ui\ModuleColumns;
use AdzerCMS\Core\Ui\Table;
use AdzerCMS\Leads\Entity\Lead;
use AdzerCMS\Leads\Entity\Stage;
use AdzerCMS\Leads\Repository\LeadRepository;
use AdzerCMS\Leads\Repository\StageRepository;
use Symfony\Component\HttpFoundation\Request;

use function Symfony\Component\Translation\t;

final readonly class LeadRows
{
    public const SLICE = 'slice';
    public const TOPIC = 'topic';
    public const MANAGER = 'manager';
    public const STAGE = 'stage';

    public function __construct(
        private LeadRepository $leads,
        private StageRepository $stages,
        private Handling $handling,
        private LoginNames $logins,
        private ModuleColumns $modules,
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

        $extra = $this->modules->columns(LeadSubject::LISTING);

        $table = Table::of($request, $route, [
            Column::of('number', t('leads.column.number'), 'l.id'),
            Column::of('topic', t('leads.field.topic'), 'l.topic'),
            Column::of('contacts', t('leads.column.contacts'), 'l.phone', 'l.email'),
            ...$extra,
            Column::of('amount', t('leads.field.amount'), 'l.amount')->aside(),
            Column::of('manager', t('leads.field.manager'), 'm.id')->aside(),
            Column::of('due', t('leads.field.due'), 'l.due'),
            Column::of('arrived', t('leads.field.arrived'), 'l.arrived')->aside(),
            Column::of('stage', t('leads.field.stage'), 's.position'),
        ], 'arrived', t('leads.empty'), false, filters: array_values(array_filter([
            $tabs,
            Filter::search(),
            [] === $topics ? null : Filter::select(self::TOPIC, t('leads.filter.topic'), array_combine($topics, $topics))->on('l.topic'),
            [] === $managers ? null : Filter::select(self::MANAGER, t('leads.filter.manager'), $managers)->on('l.manager'),
            \count($stages) < 2 ? null : Filter::select(self::STAGE, t('leads.filter.stage'), $stages)->on('l.stage'),
        ])), sticky: $sticky);

        $term = trim($table->term());

        $builder = $this->leads->listing($slice, $term, $this->modules->search(LeadSubject::LISTING, $term));
        $this->modules->order(LeadSubject::LISTING, $table, $builder, 'l.id');
        $table->apply($builder);

        /** @var list<Lead> $rows */
        $rows = $table->paginate($builder->addSelect('s', 'm')->addOrderBy('l.id', \SortDirection::Descending)->getQuery());

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
            'cells' => $this->modules->cells(LeadSubject::LISTING, array_map(static fn (Lead $lead): int => (int) $lead->getId(), $rows)),
            'managers' => $this->logins->ofMany(array_values($assigned)),
        ];
    }
}
