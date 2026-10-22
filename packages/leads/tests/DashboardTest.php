<?php

declare(strict_types=1);

namespace AdzerCMS\Leads\Tests;

use AdzerCMS\Leads\DashboardTab;
use AdzerCMS\Leads\LeadRows;
use AdzerCMS\Leads\Slice;
use AdzerCMS\Leads\StageGroup;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

use function Symfony\Component\Clock\now;

final class DashboardTest extends LeadsTestCase
{
    private const DASHBOARD = '/admin';

    /** @param list<string> $names */
    #[Group('ACMS-LEAD-0016')]
    #[Group('ACMS-US-0041')]
    #[DataProvider('slices')]
    public function testTileCountsItsSliceByInstanceDayAndLeadsToItInDashboardTab(Slice $slice, array $names): void
    {
        self::mockTime('2026-10-12 09:00');
        $this->assign($this->lead(StageGroup::Done, 'Глеб'), null, now('2026-10-20 10:00'));
        self::mockTime('2026-10-15 23:59');
        $this->assign($this->lead(StageGroup::Work, 'Вера'), null, now('2026-10-21 10:00'));
        self::mockTime('2026-10-16 00:00');
        $this->lead(StageGroup::Fresh, 'Дина');
        self::mockTime('2026-10-22 00:00');
        $this->lead(StageGroup::Fresh, 'Анна');
        self::mockTime('2026-10-22 12:00');

        $label = self::phrase('leads.dashboard.'.$slice->value);
        $page = $this->arrive(self::DASHBOARD);
        self::assertSame((string) \count($names), self::tile($page, $label));

        $tile = $page->filter('a.stat-card')->reduce(static fn (Crawler $tile): bool => $label === trim($tile->filter('.stat-label')->text()));
        $query = ['tab' => DashboardTab::KEY] + (Slice::WATCH[0] === $slice ? [] : [LeadRows::SLICE => $slice->value]);
        self::assertSame(self::DASHBOARD.'?'.http_build_query($query), $tile->attr('href'));
        self::assertEqualsCanonicalizing($names, self::names($this->fragment((string) $tile->attr('href'))));
    }

    /** @return iterable<string, array{Slice, list<string>}> */
    public static function slices(): iterable
    {
        yield 'new' => [Slice::Fresh, ['Анна', 'Дина']];
        yield 'overdue of open leads' => [Slice::Overdue, ['Вера']];
        yield 'arrived since midnight' => [Slice::Today, ['Анна']];
        yield 'arrived within seven days by today' => [Slice::Week, ['Анна', 'Дина']];
    }
}
