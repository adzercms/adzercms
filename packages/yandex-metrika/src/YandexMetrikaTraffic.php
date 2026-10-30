<?php

declare(strict_types=1);

namespace AdzerCMS\YandexMetrika;

use AdzerCMS\Contracts\Analytics\Report;
use AdzerCMS\Contracts\Analytics\Step;
use AdzerCMS\Contracts\Analytics\Traffic;
use AdzerCMS\Contracts\Analytics\Visits;
use AdzerCMS\Contracts\Refusal;

final readonly class YandexMetrikaTraffic implements Traffic
{
    public const COUNTER = 'https://api-metrika.yandex.net/management/v1/counter/%s';
    public const TOTAL = 'https://api-metrika.yandex.net/stat/v1/data?ids=%s';
    public const STEPS = 'https://api-metrika.yandex.net/stat/v1/data/bytime?ids=%s';
    public const METRICS = 'ym:s:users,ym:s:visits,ym:s:pageviews';

    private const DAY = 'Y-m-d';
    private const ACCURACY = 'full';

    public function __construct(private YandexMetrikaApi $api)
    {
    }

    public function available(): bool
    {
        return $this->api->available();
    }

    public function report(\DateTimeImmutable $from, \DateTimeImmutable $last, Step $step): Report|Refusal
    {
        $query = ['metrics' => self::METRICS, 'date1' => $from->format(self::DAY), 'date2' => $last->format(self::DAY), 'accuracy' => self::ACCURACY];
        [$counter, $total, $steps] = array_map(YandexMetrikaApi::answer(...), [
            $this->api->request('GET', self::COUNTER),
            $this->api->request('GET', self::TOTAL, ['query' => $query]),
            $this->api->request('GET', self::STEPS, ['query' => $query + ['group' => $step->value]]),
        ]);

        if ($counter instanceof Refusal) {
            return $counter;
        }
        if ($total instanceof Refusal) {
            return $total;
        }
        if ($steps instanceof Refusal) {
            return $steps;
        }

        $intervals = \is_array($steps['time_intervals'] ?? null) ? array_values($steps['time_intervals']) : [];
        $totals = \is_array($steps['totals'] ?? null) ? $steps['totals'] : [];
        $visits = [];

        foreach ($intervals as $index => $interval) {
            $bounds = \is_array($interval) ? array_values($interval) : [];
            $visits[] = new Visits(
                self::day($bounds[0] ?? null, $from),
                self::day($bounds[1] ?? null, $last),
                ...self::counts(array_map(static fn (mixed $metric): mixed => \is_array($metric) ? array_values($metric)[$index] ?? 0 : 0, $totals)),
            );
        }

        return new Report(new Visits($from, $last, ...self::counts($total['totals'] ?? null)), $visits, self::start($counter, $from));
    }

    /** @return array{int, int, int} */
    private static function counts(mixed $totals): array
    {
        $totals = \is_array($totals) ? array_values($totals) : [];

        return [self::count($totals[0] ?? 0), self::count($totals[1] ?? 0), self::count($totals[2] ?? 0)];
    }

    private static function count(mixed $value): int
    {
        return is_numeric($value) ? (int) round((float) $value) : 0;
    }

    private static function day(mixed $value, \DateTimeImmutable $otherwise): \DateTimeImmutable
    {
        $day = \is_string($value) ? \DateTimeImmutable::createFromFormat('!'.self::DAY, substr($value, 0, 10), $otherwise->getTimezone()) : false;

        return false === $day ? $otherwise : $day;
    }

    /** @param array<mixed> $counter */
    private static function start(array $counter, \DateTimeImmutable $otherwise): \DateTimeImmutable
    {
        $created = \is_array($counter['counter'] ?? null) ? $counter['counter']['create_time'] ?? null : null;

        try {
            return \is_string($created) ? (new \DateTimeImmutable($created))->setTimezone($otherwise->getTimezone())->setTime(0, 0) : $otherwise;
        } catch (\Exception) {
            return $otherwise;
        }
    }
}
