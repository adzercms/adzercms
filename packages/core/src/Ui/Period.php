<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

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
    ) {
    }

    public static function of(Request $request, \DateTimeImmutable $now): self
    {
        $year = (int) ($request->query->get('year') ?? $now->format('Y'));
        $month = (int) ($request->query->get('month') ?? $now->format('n'));
        $from = self::day((string) $request->query->get('from')) ?? $now->modify('first day of this month');
        $to = self::day((string) $request->query->get('to')) ?? $now;

        return new self(
            PeriodScale::tryFrom((string) $request->query->get('scale')) ?? PeriodScale::Month,
            $year > 0 ? $year : (int) $now->format('Y'),
            $month >= 1 && $month <= 12 ? $month : (int) $now->format('n'),
            $from->setTime(0, 0),
            ($to < $from ? $from : $to)->setTime(0, 0),
        );
    }

    public function since(): \DateTimeImmutable
    {
        if (PeriodScale::Custom === $this->scale) {
            return $this->from;
        }

        return new \DateTimeImmutable(sprintf(
            '%d-%02d-01 00:00:00',
            $this->year,
            PeriodScale::Year === $this->scale ? 1 : $this->month,
        ));
    }

    public function until(): \DateTimeImmutable
    {
        return match ($this->scale) {
            PeriodScale::Custom => $this->to->modify('+1 day'),
            PeriodScale::Year => $this->since()->modify('+1 year'),
            PeriodScale::Month => $this->since()->modify('+1 month'),
        };
    }

    public function label(): TranslatableInterface
    {
        return t('core.moment.period', ['scale' => $this->scale->value, 'since' => $this->since(), 'last' => $this->until()->modify('-1 day')]);
    }

    /** @return array<string, string> */
    public function query(): array
    {
        return [
            'scale' => $this->scale->value,
            'year' => (string) $this->year,
            'month' => (string) $this->month,
            'from' => $this->from->format(self::DAY),
            'to' => $this->to->format(self::DAY),
        ];
    }

    /** @return list<Filter> */
    public function filters(int $first, \DateTimeImmutable $now): array
    {
        $years = [];
        foreach ($this->years($first, $now) as $year) {
            $years[(string) $year] = (string) $year;
        }

        $own = PeriodScale::Custom === $this->scale;

        return [
            Filter::choice('scale', Labels::of(PeriodScale::cases()), $this->scale->value),
            Filter::choice('year', $years, (string) $this->year, $own),
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
