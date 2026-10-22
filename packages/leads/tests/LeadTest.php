<?php

declare(strict_types=1);

namespace AdzerCMS\Leads\Tests;

use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Leads\Repository\LeadRepository;
use AdzerCMS\Leads\StageGroup;
use AdzerCMS\Leads\Tests\Fixture\ItemSubject;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

final class LeadTest extends LeadsTestCase
{
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

    #[Group('ACMS-LEAD-0007')]
    #[Group('ACMS-US-0041')]
    public function testDropAsksAndTakesLeadAway(): void
    {
        $number = (int) $this->lead(StageGroup::Work, 'Анна', '79990000002')->getId();

        $drop = $this->arrive(self::LIST.'/'.$number)->filter('form[data-confirm]');
        self::assertSame(self::phrase('leads.lead.drop_confirm', ['number' => $number]), $drop->attr('data-confirm'));
        $this->post((string) $drop->attr('action'));

        self::assertSame(self::phrase('leads.flash.dropped', ['number' => $number]), self::toast($this->arrive(self::LIST), 'success'));
        self::assertNull(self::service(LeadRepository::class)->find($number));
    }
}
