<?php

declare(strict_types=1);

namespace AdzerCMS\Admin;

use AdzerCMS\Contracts\Analytics\Report;
use AdzerCMS\Contracts\Analytics\Step;
use AdzerCMS\Contracts\Analytics\Traffic;
use AdzerCMS\Contracts\Analytics\Visits;
use AdzerCMS\Contracts\Ui\AdminEntry;
use AdzerCMS\Contracts\Ui\Tab;
use AdzerCMS\Contracts\Ui\Tabs;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Core\Ui\Period;
use AdzerCMS\Core\Ui\PeriodScale;
use AdzerCMS\Core\Ui\Table;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Clock\now;
use function Symfony\Component\Translation\t;

final readonly class TrafficTab implements Tabs
{
    private const KEY = 'traffic';
    private const STEP = 'step';
    private const DAY = 'Y-m-d';

    /** @param iterable<Traffic> $reports */
    public function __construct(
        #[AutowireIterator(Traffic::class)]
        private iterable $reports,
    ) {
    }

    public function page(): string
    {
        return self::ADMIN_DASHBOARD;
    }

    public function tabs(?int $subject): array
    {
        return null === $this->available() ? [] : [new Tab(self::KEY, t('admin.traffic.tab'), 900)];
    }

    public function tab(string $key, ?int $subject, Request $request, array $sticky): array
    {
        $traffic = $this->available() ?? throw new \LogicException('The traffic tab is offered only with an available report');
        $now = now();
        $today = $now->setTime(0, 0);
        $period = Period::of($request, $now);
        $steps = Filter::choice(self::STEP, array_combine(
            array_map(static fn (Step $step): string => $step->value, Step::cases()),
            array_map(static fn (Step $step): TranslatableInterface => t('admin.traffic.step.'.$step->value), Step::cases()),
        ), Step::Day->value);
        $from = $period->since();
        $last = min($period->until()->modify('-1 day'), $today);
        $report = $from > $last ? null : $traffic->report($from, $last, Step::from($steps->pick((string) $request->query->get(self::STEP))));
        $lead = $report instanceof Report ? [self::row($report->total, t('admin.traffic.total'), true)] : [];
        $rows = $report instanceof Report ? array_map(static fn (Visits $visits): array => self::row($visits, self::label($visits)), $report->steps) : [];

        $table = Table::of($request, AdminEntry::Dashboard->route(), [
            Column::live('step', t('admin.traffic.moment'), static fn (array $row): \DateTimeImmutable => $row['visits']->from),
            Column::live('visitors', t('admin.traffic.visitors'), static fn (array $row): int => $row['visits']->visitors),
            Column::live('visits', t('admin.traffic.visits'), static fn (array $row): int => $row['visits']->visits),
            Column::live('views', t('admin.traffic.views'), static fn (array $row): int => $row['visits']->views),
        ], 'step', null === $report || $report instanceof Report ? t('admin.traffic.empty') : $report->reason, filters: [
            ...$period->filters((int) ($report instanceof Report ? $report->since : $today)->format('Y'), $now),
            $steps,
        ], size: \count($rows) + 1, sticky: $sticky, live: true);

        return ['template' => '@Admin/admin/_traffic.html.twig', 'context' => ['table' => $table, 'rows' => $table->rows($rows, $lead)]];
    }

    private function available(): ?Traffic
    {
        foreach ($this->reports as $report) {
            if ($report->available()) {
                return $report;
            }
        }

        return null;
    }

    /** @return array{label: TranslatableInterface, visits: Visits, lead: bool} */
    private static function row(Visits $visits, TranslatableInterface $label, bool $lead = false): array
    {
        return ['label' => $label, 'visits' => $visits, 'lead' => $lead];
    }

    private static function label(Visits $visits): TranslatableInterface
    {
        if ($visits->from->format(self::DAY) === $visits->last->format(self::DAY)) {
            return t('core.moment.day', ['at' => $visits->from]);
        }

        $month = '1' === $visits->from->format('j') && $visits->from->modify('last day of this month')->format(self::DAY) === $visits->last->format(self::DAY);

        return t('core.moment.period', ['scale' => ($month ? PeriodScale::Month : PeriodScale::Custom)->value, 'since' => $visits->from, 'last' => $visits->last]);
    }
}
