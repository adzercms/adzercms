<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Ui;

use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Core\Ui\Period;
use AdzerCMS\Core\Ui\PeriodScale;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class PeriodTest extends TestCase
{
    /**
     * @param array<string, string> $query
     * @param list<string>          $scales
     */
    #[Group('ACMS-FLOW-0002')]
    #[DataProvider('periods')]
    public function testLedgerDefaultsToTheWholeTermAndCarriesOnlyAChosenPeriod(bool $ledger, array $query, PeriodScale $scale, array $scales, bool $carried): void
    {
        $now = new \DateTimeImmutable('2026-11-12');
        $request = new Request($query);
        $period = $ledger ? Period::ledger($request, $now) : Period::of($request, $now);
        $filters = $period->filters(2025, $now);
        $shown = array_values(array_map(static fn (Filter $filter): string => $filter->name, array_filter($filters, static fn (Filter $filter): bool => !$filter->hidden)));

        self::assertSame($scale, $period->scale);
        self::assertSame($scales, array_map('strval', array_keys($filters[0]->options)));
        self::assertSame($carried, [] !== $period->query());
        self::assertSame(PeriodScale::All === $scale ? ['scale'] : ['scale', 'year', 'month'], $shown);
        self::assertSame(PeriodScale::All === $scale ? [null, null] : ['2026-11-01', '2026-12-01'],
            array_map(static fn (?\DateTimeImmutable $bound): ?string => $bound?->format('Y-m-d'), $period->bounds()));
    }

    /** @return iterable<string, array{bool, array<string, string>, PeriodScale, list<string>, bool}> */
    public static function periods(): iterable
    {
        $bounded = ['month', 'year', 'custom'];

        yield 'ledger by default' => [true, [], PeriodScale::All, [...$bounded, 'all'], false];
        yield 'ledger with a chosen month' => [true, ['scale' => 'month', 'year' => '2026', 'month' => '11'], PeriodScale::Month, [...$bounded, 'all'], true];
        yield 'summary by default' => [false, [], PeriodScale::Month, $bounded, false];
        yield 'summary given the whole term' => [false, ['scale' => 'all'], PeriodScale::Month, $bounded, false];
    }
}
