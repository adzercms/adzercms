<?php

declare(strict_types=1);

namespace AdzerCMS\Admin\Tests;

use AdzerCMS\Admin\Tests\Fixture\Footfall;
use AdzerCMS\Contracts\Analytics\Step;
use AdzerCMS\Contracts\Analytics\Visits;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Translation\TranslatableMessage;

use function Symfony\Component\Clock\now;

final class TrafficWebTest extends SchemaTestCase
{
    use Accounts;
    use Browser;

    private const PAGE = '/admin/traffic';

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->loginUser($this->record('79990000001')->setRole(Role::Admin));
        $this->em->flush();
    }

    #[Group('ACMS-CORE-1027')]
    #[Group('ACMS-US-0068')]
    public function testTrafficOfThePeriodGoesByStepsUnderItsTotalWhileACounterReports(): void
    {
        $footfall = self::service(Footfall::class);
        $today = now()->setTime(0, 0);
        $year = (int) $today->format('Y');

        self::assertCount(0, $this->arrive('/admin')->filter('a[href="'.self::PAGE.'"]'), 'without a report the section is not in the menu');
        $this->client->request('GET', self::PAGE);
        self::assertResponseStatusCodeSame(404);

        $footfall->open = true;
        $footfall->since = new \DateTimeImmutable(($year - 2).'-05-14');
        $footfall->steps = [new Visits($today, $today, 4, 5, 12)];
        $page = $this->arrive(self::PAGE);
        self::assertGreaterThan(0, $page->filter('a[href="'.self::PAGE.'"]')->count());
        self::assertEquals([[$today->modify('first day of this month'), $today, Step::Day]], $footfall->asked, 'the current month by days up to today');
        self::assertSame([
            [self::phrase('admin.traffic.total'), ...array_map(strval(...), Footfall::TOTAL)],
            [self::phrase('core.moment.day', ['at' => $today]), '4', '5', '12'],
        ], self::rows($page));
        self::assertSame(array_map(strval(...), range($year - 2, $year)), $page->filter('select[name="year"] option')->each(static fn (Crawler $option): string => (string) $option->attr('value')), 'years from the start of the statistics');
        self::assertSame(self::phrase('admin.traffic.total'), self::rows($this->arrive(self::PAGE.'?sort=visitors&order=desc'))[0][0], 'the total stays over the steps');

        $january = new \DateTimeImmutable(($year - 1).'-01-01');
        $footfall->steps = [new Visits($january, $january->modify('last day of this month'), 30, 40, 90)];
        $footfall->asked = [];
        self::assertSame([self::phrase('core.moment.period', ['scale' => 'month', 'since' => $january, 'last' => $january])], array_slice(array_column(self::rows($this->arrive(self::PAGE.'?scale=year&year='.($year - 1).'&step=month')), 0), 1));
        self::assertEquals([[$january, $january->modify('last day of december'), Step::Month]], $footfall->asked);

        $from = $today->modify('-2 days');
        $footfall->steps = [new Visits($from, $today, 6, 7, 15)];
        $footfall->asked = [];
        self::assertSame(self::phrase('core.moment.period', ['scale' => 'custom', 'since' => $from, 'last' => $today]), self::rows($this->arrive(self::PAGE.'?scale=custom&from='.$from->format('Y-m-d').'&to='.$today->modify('+5 days')->format('Y-m-d').'&step=week'))[1][0]);
        self::assertEquals([[$from, $today, Step::Week]], $footfall->asked, 'days after today stay out of the period');

        $footfall->asked = [];
        self::assertSame(self::phrase('admin.traffic.empty'), trim($this->arrive(self::PAGE.'?scale=year&year='.($year + 1))->filter('.empty-state')->text()));
        self::assertSame([], $footfall->asked, 'a period ahead asks nothing');

        $footfall->refusal = new Refusal(new TranslatableMessage('Counter is offline'));
        self::assertSame('Counter is offline', trim($this->arrive(self::PAGE)->filter('.empty-state')->text()));
    }

    /** @return list<list<string>> */
    private static function rows(Crawler $page): array
    {
        return $page->filter('#rows tbody tr')->each(static fn (Crawler $row): array => $row->filter('td')->each(static fn (Crawler $cell): string => trim($cell->text())));
    }
}
