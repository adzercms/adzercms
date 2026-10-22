<?php

declare(strict_types=1);

namespace AdzerCMS\Leads\Tests;

use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Account\LoginNames;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Account\UserRemoval;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Ui\Money;
use AdzerCMS\Leads\Entity\Lead;
use AdzerCMS\Leads\Entity\LeadEntry;
use AdzerCMS\Leads\Entity\Stage;
use AdzerCMS\Leads\Handling;
use AdzerCMS\Leads\LeadSettings;
use AdzerCMS\Leads\Repository\LeadEntryRepository;
use AdzerCMS\Leads\Repository\LeadRepository;
use AdzerCMS\Leads\Repository\StageRepository;
use AdzerCMS\Leads\StageGroup;
use AdzerCMS\Leads\Stages;
use AdzerCMS\Leads\Tests\Fixture\ItemSubject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

final class LeadTest extends LeadsTestCase
{
    use Configuration;

    #[Group('ACMS-LEAD-0008')]
    #[Group('ACMS-US-0041')]
    public function testListRowLeadsToPageWithClientLinksMessageFormPageAndSubjectDetails(): void
    {
        $lead = $this->lead(StageGroup::Fresh, 'Анна', '79990000002', 'anna@site.test', 'Нужен кран на неделю', 'Кран', page: '/catalog/kran');
        self::service(ItemSubject::class)->attached[] = $lead;

        $page = $this->arrive((string) $this->fragment(self::LIST)->filter('tbody tr')->attr('data-href'));

        self::assertSame(
            ['Анна', '79990000002', 'anna@site.test', 'Кран', '/catalog/kran', ItemSubject::PRICE],
            array_map(static fn (string $label): string => self::tile($page, $label), [
                self::phrase('leads.field.name'),
                self::phrase(ContactKind::Phone->label()),
                self::phrase(ContactKind::Email->label()),
                self::phrase('leads.field.topic'),
                self::phrase('leads.field.page'),
                ItemSubject::LABEL,
            ]),
        );
        self::assertSame(['tel:+79990000002', 'mailto:anna@site.test', '/catalog/kran'], $page->filter('a.info-value')->each(static fn (Crawler $link): string => (string) $link->attr('href')));
        self::assertSame('Нужен кран на неделю', trim($page->filter('.chat-text')->text()));
    }

    #[Group('ACMS-LEAD-0015')]
    #[Group('ACMS-US-0041')]
    public function testOtherLeadsOfClientMatchByPhoneOrEmail(): void
    {
        $lead = $this->lead(StageGroup::Fresh, 'Анна', '79990000002', 'anna@site.test');
        $byPhone = $this->lead(StageGroup::Done, 'Анна', '79990000002');
        $byEmail = $this->lead(StageGroup::Work, 'Аня', null, 'anna@site.test');
        $this->lead(StageGroup::Fresh, 'Борис', '79990000003', 'boris@site.test');

        self::assertSame(
            [self::LIST.'/'.$byEmail->getId(), self::LIST.'/'.$byPhone->getId()],
            $this->arrive(self::LIST.'/'.$lead->getId())->filter('.info-value a[href^="'.self::LIST.'/"]')->each(static fn (Crawler $link): string => (string) $link->attr('href')),
        );
    }

    #[Group('ACMS-LEAD-0007')]
    #[Group('ACMS-US-0041')]
    public function testDropAsksAndTakesLeadAwayWithItsFeed(): void
    {
        $number = (int) $this->lead(StageGroup::Work, 'Анна', '79990000002')->getId();
        $this->post(self::LIST.'/'.$number.'/note', [Handling::NOTE => 'Перезвонить']);

        $drop = $this->arrive(self::LIST.'/'.$number)->filter('form[data-confirm]');
        self::assertSame(self::phrase('leads.lead.drop_confirm', ['number' => $number]), $drop->attr('data-confirm'));
        $this->post((string) $drop->attr('action'));

        self::assertSame(self::phrase('leads.flash.dropped', ['number' => $number]), self::toast($this->arrive(self::LIST), 'success'));
        self::assertNull(self::service(LeadRepository::class)->find($number));
        self::assertSame(0, self::service(LeadEntryRepository::class)->count([]));
    }

    #[Group('ACMS-LEAD-0014')]
    #[Group('ACMS-US-0041')]
    public function testFeedGoesOldToNewWithMessageNotesAndSavedChangesOnly(): void
    {
        $lead = $this->lead(StageGroup::Fresh, 'Анна', '79990000002', message: 'Перезвоните');
        $call = self::service(Stages::class)->add(StageGroup::Work, 'Звонок');
        self::assertInstanceOf(Stage::class, $call);
        $page = self::LIST.'/'.$lead->getId();
        $handled = [Handling::STAGE => $call->getId(), Handling::MANAGER => $this->operator->getId(), Handling::DUE => '2026-11-02T10:30', Handling::AMOUNT => '1500'];

        $this->post($page.'/note', [Handling::NOTE => 'Позвонить утром']);
        $this->post($page, $handled);
        $this->post($page, [Handling::AMOUNT => '1 500,00'] + $handled);

        $operator = self::service(LoginNames::class)->of($this->operator);
        $feed = $this->arrive($page)->filter('.chat-message');
        self::assertSame(['Анна', $operator, $operator], $feed->each(static fn (Crawler $entry): string => trim($entry->filter('.chat-author')->text())));
        self::assertSame(['Перезвоните', 'Позвонить утром'], $feed->slice(0, 2)->each(static fn (Crawler $entry): string => trim($entry->filter('.chat-text')->text())));
        self::assertSame(
            [
                self::phrase('leads.field.stage').': '.self::phrase(StageGroup::Fresh->label()).' → Звонок',
                self::phrase('leads.field.manager').': — → '.$operator,
                self::phrase('leads.field.due').': — → '.self::phrase('core.moment.day_time', ['at' => new \DateTimeImmutable('2026-11-02 10:30')]),
                self::phrase('leads.field.amount').': — → '.self::phrase(Money::rubles('1500')),
            ],
            $feed->eq(2)->filter('.chat-text div')->each(static fn (Crawler $change): string => trim($change->text())),
        );
    }

    #[Group('ACMS-LEAD-0004')]
    #[Group('ACMS-US-0041')]
    #[DataProvider('refusals')]
    public function testRefusalStageNeedsReasonOfSettingsOrOwnAndOtherGroupDropsIt(StageGroup $group, string $reason, bool $refused, ?string $kept): void
    {
        $this->configure([LeadSettings::REASONS => 'Дорого; Выбрал другого']);
        $lead = $this->lead(StageGroup::Fresh, 'Анна', '79990000002');
        $lead->handle(self::service(StageRepository::class)->first(StageGroup::Refused), null, null, null, 'Не дозвонились');
        $this->em->flush();
        $page = self::LIST.'/'.$lead->getId();

        self::assertSame(['Дорого', 'Выбрал другого'], $this->arrive($page)->filter('datalist option')->each(static fn (Crawler $option): string => (string) $option->attr('value')));
        $answer = $this->post($page, [Handling::STAGE => self::service(StageRepository::class)->first($group)->getId(), Handling::REASON => $reason]);

        self::assertSame($refused ? [self::phrase('leads.refusal.reason', ['limit' => Lead::REASON])] : [], $answer->filter('.field-refusal')->each(static fn (Crawler $refusal): string => trim($refusal->text())));
        self::assertSame($kept, $this->reread($lead)->getReason());
    }

    /** @return iterable<string, array{StageGroup, string, bool, ?string}> */
    public static function refusals(): iterable
    {
        yield 'refusal without a reason' => [StageGroup::Refused, '', true, 'Не дозвонились'];
        yield 'refusal with a reason' => [StageGroup::Refused, 'Купили у соседа', false, 'Купили у соседа'];
        yield 'another group' => [StageGroup::Work, 'Дорого', false, null];
    }

    #[Group('ACMS-LEAD-0007')]
    #[Group('ACMS-US-0041')]
    public function testRemovedUserLeavesLeadWithoutManagerAndFeedWithoutAuthor(): void
    {
        $manager = $this->record('79990000009')->setRole(Role::Admin);
        $lead = $this->lead(StageGroup::Work, 'Анна', '79990000002');
        $lead->handle($lead->getStage(), $manager, null, null, null);
        $this->em->persist($entry = new LeadEntry($lead, $manager, 'Перезвонить'));
        $this->em->flush();

        self::service(UserRemoval::class)->remove($manager);
        $this->em->clear();

        self::assertNull($this->reread($lead)->getManager());
        self::assertNull($this->reread($entry)->getAuthor());
    }
}
