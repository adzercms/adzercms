<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

final readonly class Period
{
    private const DAY = 'Y-m-d';

    public function __construct(
        public PeriodScale $scale,
        public int $year,
        public int $month,
        public \DateTimeImmutable $from = new DatePoint(),
        public \DateTimeImmutable $to = new DatePoint(),
        private bool $ledger = false,
        private bool $chosen = true,
    ) {
    }

    public static function of(Request $request, \DateTimeImmutable $now): self
    {
        return self::read($request, $now, false);
    }

    public static function ledger(Request $request, \DateTimeImmutable $now): self
    {
        return self::read($request, $now, true);
    }

    /** @return array{?\DateTimeImmutable, ?\DateTimeImmutable} */
    public function bounds(): array
    {
        return PeriodScale::All === $this->scale ? [null, null] : [$this->since(), $this->until()];
    }

    public function narrow(QueryBuilder $builder, string $field): QueryBuilder
    {
        if (PeriodScale::All === $this->scale) {
            return $builder;
        }

        return $builder->andWhere(\sprintf('%1$s >= :since AND %1$s < :until', $field))
            ->setParameter('since', $this->since())
            ->setParameter('until', $this->until());
    }

    public function since(): \DateTimeImmutable
    {
        return match ($this->scale) {
            PeriodScale::Custom => $this->from,
            PeriodScale::All => throw new \LogicException('The whole term has no bounds.'),
            PeriodScale::Month, PeriodScale::Year => new \DateTimeImmutable(\sprintf(
                '%d-%02d-01 00:00:00',
                $this->year,
                PeriodScale::Year === $this->scale ? 1 : $this->month,
            )),
        };
    }

    public function until(): \DateTimeImmutable
    {
        return match ($this->scale) {
            PeriodScale::Custom => $this->to->modify('+1 day'),
            PeriodScale::Year => $this->since()->modify('+1 year'),
            PeriodScale::Month => $this->since()->modify('+1 month'),
            PeriodScale::All => throw new \LogicException('The whole term has no bounds.'),
        };
    }

    public function label(): TranslatableInterface
    {
        return t('core.moment.period', ['scale' => $this->scale->value, 'since' => $this->since(), 'last' => $this->until()->modify('-1 day')]);
    }

    /** @return array<string, string> */
    public function query(): array
    {
        return $this->chosen ? [
            'scale' => $this->scale->value,
            'year' => (string) $this->year,
            'month' => (string) $this->month,
            'from' => $this->from->format(self::DAY),
            'to' => $this->to->format(self::DAY),
        ] : [];
    }

    /** @return list<Filter> */
    public function filters(int $first, \DateTimeImmutable $now): array
    {
        $years = [];
        foreach ($this->years($first, $now) as $year) {
            $years[(string) $year] = (string) $year;
        }

        $own = PeriodScale::Custom === $this->scale;
        $scales = $this->ledger ? PeriodScale::cases() : array_filter(PeriodScale::cases(), static fn (PeriodScale $scale): bool => PeriodScale::All !== $scale);

        return [
            Filter::choice('scale', Labels::of($scales), $this->scale->value),
            Filter::choice('year', $years, (string) $this->year, $own || PeriodScale::All === $this->scale),
            Filter::choice('month', self::months(), (string) $this->month, PeriodScale::Month !== $this->scale),
            Filter::date('from', $this->from->format(self::DAY), !$own),
            Filter::date('to', $this->to->format(self::DAY), !$own),
        ];
    }

    /** @return list<int> */
    public function years(int $first, \DateTimeImmutable $now): array
    {
        $last = max($first, $this->year, (int) $now->format('Y'));

        return range(min($first, $this->year), $last);
    }

    private static function read(Request $request, \DateTimeImmutable $now, bool $ledger): self
    {
        $year = (int) ($request->query->get('year') ?? $now->format('Y'));
        $month = (int) ($request->query->get('month') ?? $now->format('n'));
        $from = self::day((string) $request->query->get('from')) ?? $now->modify('first day of this month');
        $to = self::day((string) $request->query->get('to')) ?? $now;
        $scale = PeriodScale::tryFrom((string) $request->query->get('scale'));
        if (PeriodScale::All === $scale && !$ledger) {
            $scale = null;
        }

        return new self(
            $scale ?? ($ledger ? PeriodScale::All : PeriodScale::Month),
            $year > 0 ? $year : (int) $now->format('Y'),
            $month >= 1 && $month <= 12 ? $month : (int) $now->format('n'),
            $from->setTime(0, 0),
            ($to < $from ? $from : $to)->setTime(0, 0),
            $ledger,
            null !== $scale,
        );
    }

    private static function day(string $value): ?\DateTimeImmutable
    {
        $day = \DateTimeImmutable::createFromFormat(self::DAY, $value);

        return false === $day ? null : $day;
    }

    /** @return array<int, TranslatableInterface> */
    private static function months(): array
    {
        $months = [];
        for ($month = 1; $month <= 12; ++$month) {
            $months[$month] = t('core.moment.month', ['at' => new \DateTimeImmutable(\sprintf('2000-%02d-01', $month))]);
        }

        return $months;
    }
}
