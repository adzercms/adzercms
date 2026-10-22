<?php

declare(strict_types=1);

namespace AdzerCMS\Leads\Tests;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Tests\Queries;
use AdzerCMS\Leads\Entity\Lead;
use AdzerCMS\Leads\Entity\Stage;
use AdzerCMS\Leads\Intake;
use AdzerCMS\Leads\LeadRows;
use AdzerCMS\Leads\Repository\LeadRepository;
use AdzerCMS\Leads\Repository\StageRepository;
use AdzerCMS\Leads\Slice;
use AdzerCMS\Leads\StageGroup;
use AdzerCMS\Leads\Stages;
use AdzerCMS\Leads\Tests\Fixture\ItemSubject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

use function Symfony\Component\Clock\now;

final class ListTest extends LeadsTestCase
{
    use Queries;

    #[Group('ACMS-LEAD-0005')]
    #[Group('ACMS-US-0041')]
    public function testSlicesSplitLeadsByStageGroupsWithCountsAndMenuCountsNewOnes(): void
    {
        $this->lead(StageGroup::Fresh, 'Анна');
        $this->lead(StageGroup::Fresh, 'Борис');
        $this->lead(StageGroup::Work, 'Вера');
        $this->lead(StageGroup::Done, 'Глеб');
        $this->lead(StageGroup::Refused, 'Дина');

        $page = $this->arrive(self::LIST);

        self::assertSame('2', trim($page->filter('a.nav-item[href="'.self::LIST.'"] .badge')->text()));
        self::assertSame(
            [self::phrase('leads.slice.new', ['count' => 2]), self::phrase('leads.slice.work', ['count' => 1]), self::phrase('leads.slice.closed', ['count' => 2])],
            $page->filter('nav.tabs a[hx-select]')->each(static fn (Crawler $tab): string => trim($tab->text())),
        );
        self::assertEqualsCanonicalizing(['Анна', 'Борис'], self::names($page));
        self::assertEqualsCanonicalizing(['Глеб', 'Дина'], self::names($this->fragment(self::LIST.'?'.LeadRows::SLICE.'='.Slice::Closed->value)));
    }

    #[Group('ACMS-UI-0065')]
    #[Group('ACMS-US-0041')]
    #[DataProvider('terms')]
    public function testSearchFindsLeadByNumberNameContactsMessageAndSubjectModule(string $term, string $found): void
    {
        $this->lead(StageGroup::Fresh, 'Анна', null, 'anna@site.test', 'Нужен кран на неделю');
        $boris = $this->lead(StageGroup::Fresh, 'Борис', '79990000003', null, 'Перезвоните');
        self::service(ItemSubject::class)->attached[] = $boris;

        $term = '#' === $term ? (string) $boris->getId() : $term;

        self::assertSame([$found], self::names($this->fragment(self::LIST.'?q='.rawurlencode($term))));
    }

    /** @return iterable<string, array{string, string}> */
    public static function terms(): iterable
    {
        yield 'number' => ['#', 'Борис'];
        yield 'name' => ['анн', 'Анна'];
        yield 'phone as typed' => ['+7 (999) 000-00-03', 'Борис'];
        yield 'email' => ['anna@', 'Анна'];
        yield 'message' => ['кран', 'Анна'];
        yield 'subject module' => [ItemSubject::LABEL, 'Борис'];
    }

    #[Group('ACMS-LEAD-0005')]
    #[Group('ACMS-US-0041')]
    public function testFiltersNarrowByTopicManagerAndStageOfSlice(): void
    {
        $manager = $this->record('79990000009')->setRole(Role::Admin);
        $call = self::service(Stages::class)->add(StageGroup::Work, 'Звонок');
        self::assertInstanceOf(Stage::class, $call);
        $this->assign($this->lead(StageGroup::Fresh, 'Анна', topic: 'Кран'), $manager);
        $this->lead(StageGroup::Fresh, 'Борис', topic: 'Аренда');
        $this->lead(StageGroup::Work, 'Вера');
        $this->lead(StageGroup::Work, 'Глеб', stage: $call);

        $topics = $this->fragment(self::LIST);
        self::assertSame(['', 'Аренда', 'Кран'], $topics->filter('select[name="'.LeadRows::TOPIC.'"] option')->each(static fn (Crawler $option): string => (string) $option->attr('value')));
        self::assertSame(['Анна'], self::names($this->fragment(self::LIST.'?'.LeadRows::TOPIC.'='.rawurlencode('Кран'))));
        self::assertSame(['Анна'], self::names($this->fragment(self::LIST.'?'.LeadRows::MANAGER.'='.$manager->getId())));

        $work = $this->fragment(self::LIST.'?'.LeadRows::SLICE.'='.Slice::Work->value);
        $stages = array_map(static fn (Stage $stage): string => (string) $stage->getId(), self::service(StageRepository::class)->ofGroup(StageGroup::Work));
        self::assertSame(['', ...$stages], $work->filter('select[name="'.LeadRows::STAGE.'"] option')->each(static fn (Crawler $option): string => (string) $option->attr('value')));
        self::assertSame(['Глеб'], self::names($this->fragment(self::LIST.'?'.LeadRows::SLICE.'='.Slice::Work->value.'&'.LeadRows::STAGE.'='.$call->getId())));
    }

    #[Group('ACMS-LEAD-0006')]
    #[Group('ACMS-US-0041')]
    #[DataProvider('dues')]
    public function testDuePastOfOpenLeadIsRed(StageGroup $group, string $due, bool $red): void
    {
        $this->assign($this->lead($group, 'Анна', '79990000002'), null, now($due));

        $listing = $this->fragment(self::LIST.(Slice::Fresh === $group->slice() ? '' : '?'.LeadRows::SLICE.'='.$group->slice()->value));

        self::assertSame($red, \in_array('minus', explode(' ', (string) self::cell($listing, 'Анна', self::phrase('leads.field.due'))->attr('class')), true));
    }

    /** @return iterable<string, array{StageGroup, string, bool}> */
    public static function dues(): iterable
    {
        yield 'past due of a lead in work' => [StageGroup::Work, '-1 hour', true];
        yield 'coming due' => [StageGroup::Work, '+1 hour', false];
        yield 'past due of a closed lead' => [StageGroup::Done, '-1 hour', false];
    }

    #[Group('ACMS-LEAD-0004')]
    #[Group('ACMS-US-0041')]
    public function testOperatorFilesCallLeadByDialogToFirstNewStage(): void
    {
        $refused = $this->post(self::LIST.'/new', [Intake::TOPIC => 'Кран', Intake::NAME => 'Анна']);
        self::assertSame(self::phrase('leads.refusal.contact'), trim($refused->filter('.field-refusal')->text()));
        self::assertSame(0, self::service(LeadRepository::class)->count([]));

        $this->post(self::LIST.'/new', [Intake::TOPIC => 'Кран', Intake::NAME => 'Анна', Intake::PHONE => '8 (999) 000-00-02', Intake::MESSAGE => 'Звонок']);

        self::assertResponseHeaderSame('HX-Redirect', self::LIST);
        $lead = self::service(LeadRepository::class)->findOneBy([]) ?? self::fail('the lead is not filed');
        self::assertSame(
            [self::service(StageRepository::class)->first(StageGroup::Fresh)->getId(), 'Кран', 'Анна', '79990000002', 'Звонок', null],
            [$lead->getStage()->getId(), $lead->getTopic(), $lead->getName(), $lead->getPhone(), $lead->getMessage(), $lead->getPage()],
        );
        self::assertSame(self::phrase('leads.flash.added', ['number' => $lead->getId()]), self::toast($this->arrive(self::LIST), 'success'));
    }

    #[Group('ACMS-UI-0015')]
    #[Group('ACMS-UI-0065')]
    public function testListingTakesManagersAndSubjectCellsByOneQuery(): void
    {
        $manager = $this->record('79990000009')->setRole(Role::Admin);
        $this->em->flush();

        $this->assertListingKeepsItsQueries(self::LIST, function (int $from, int $to) use ($manager): void {
            for ($number = $from; $number <= $to; ++$number) {
                $lead = $this->lead(StageGroup::Fresh, 'Клиент '.$number, \sprintf('7999100%04d', $number));
                self::service(ItemSubject::class)->attached[] = $lead;
                $this->assign($lead, $manager);
            }
        });

        self::assertSame(ItemSubject::PRICE, trim(self::cell($this->fragment(self::LIST), 'Клиент 1', ItemSubject::LABEL)->text()));
    }

    private function assign(Lead $lead, ?User $manager, ?\DateTimeImmutable $due = null): void
    {
        $lead->handle($lead->getStage(), null === $manager ? null : $this->reread($manager), $due, $lead->getAmount(), $lead->getReason());
        $this->em->flush();
    }

    /** @return list<string> */
    private static function names(Crawler $listing): array
    {
        return $listing->filter('tbody tr td:first-child .clip')->each(static fn (Crawler $name): string => trim($name->text()));
    }
}
