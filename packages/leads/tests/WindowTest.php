<?php

declare(strict_types=1);

namespace AdzerCMS\Leads\Tests;

use AdzerCMS\Core\Account\Consents;
use AdzerCMS\Core\Security\CsrfGuard;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Ui\Back;
use AdzerCMS\Leads\Entity\Lead;
use AdzerCMS\Leads\Intake;
use AdzerCMS\Leads\Repository\LeadRepository;
use AdzerCMS\Leads\Repository\StageRepository;
use AdzerCMS\Leads\StageGroup;
use AdzerCMS\Leads\Tests\Fixture\ItemSubject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

final class WindowTest extends SchemaTestCase
{
    use Accounts;
    use Browser;
    use Configuration;

    private const WINDOW = '/lead';
    private const PAGE = '/fixture/frame?view=card';
    private const POLICY = 'https://site.test/privacy';
    private const TOPIC = 'Кран КС-45717';

    #[Group('ACMS-LEAD-0009')]
    #[Group('ACMS-LEAD-0011')]
    #[Group('ACMS-US-0040')]
    public function testWindowOpensOverAnyPageWithTopicAndSubjectFieldsOfItsOpener(): void
    {
        $this->configure([Consents::PRIVACY_URL => self::POLICY]);

        $general = $this->fragment(self::WINDOW, self::PAGE);
        self::assertSame(self::phrase('leads.window.title'), trim($general->filter('.modal-header h2')->text()));
        self::assertCount(0, $general->filter('input[name="'.ItemSubject::FIELD.'"]'));
        self::assertSame(self::POLICY, $general->filter('form p.hint a')->attr('href'), 'the policy link goes under the send button');

        $item = $this->fragment(self::WINDOW.'?'.http_build_query([Intake::TOPIC => self::TOPIC, ItemSubject::FIELD => '1']), self::PAGE);
        self::assertSame(self::TOPIC, trim($item->filter('.modal-header h2')->text()));
        self::assertSame(self::TOPIC, $item->filter('input[name="'.Intake::TOPIC.'"]')->attr('value'));
        self::assertCount(1, $item->filter('input[name="'.ItemSubject::FIELD.'"]'));

        $this->client->loginUser($this->record('79990000001'));
        self::assertSame(self::WINDOW, $this->client->request('GET', self::PAGE)->filter('dialog#lead')->attr('data-window'), 'the window waits at the end of any page');
    }

    #[Group('ACMS-LEAD-0004')]
    #[Group('ACMS-LEAD-0008')]
    #[Group('ACMS-LEAD-0010')]
    #[Group('ACMS-US-0040')]
    public function testAcceptedLeadGoesToFirstNewStageWithItsPageContactsAndSubjectAmount(): void
    {
        $first = self::service(StageRepository::class)->first(StageGroup::Fresh);
        $this->client->loginUser($this->record('79990000001'));

        $this->take([Intake::TOPIC => self::TOPIC, ItemSubject::FIELD => '1', Intake::NAME => 'Анна', Intake::PHONE => '8 (999) 000-00-02', Intake::EMAIL => 'Anna@Site.Test', Intake::MESSAGE => 'Нужен на неделю']);

        self::assertResponseHeaderSame('HX-Redirect', self::PAGE);
        $lead = self::service(LeadRepository::class)->findOneBy([]) ?? self::fail('the lead is not filed');
        self::assertSame(
            [$first->getId(), self::TOPIC, 'Анна', '79990000002', 'anna@site.test', 'Нужен на неделю', '/fixture/frame', ItemSubject::PRICE],
            [$lead->getStage()->getId(), $lead->getTopic(), $lead->getName(), $lead->getPhone(), $lead->getEmail(), $lead->getMessage(), $lead->getPage(), $lead->getAmount()],
        );
        self::assertSame([$lead->getId()], array_map(static fn (Lead $attached): ?int => $attached->getId(), self::service(ItemSubject::class)->attached));
        self::assertSame(self::phrase('leads.flash.taken'), self::toast($this->arrive(self::PAGE), 'success'));
    }

    /** @param array<string, string> $fields */
    #[Group('ACMS-LEAD-0008')]
    #[Group('ACMS-LEAD-0010')]
    #[Group('ACMS-US-0040')]
    #[DataProvider('refusals')]
    public function testRefusedLeadKeepsInputWithReasonsBesideFields(array $fields, bool $sold, string $field, string $reason): void
    {
        self::service(ItemSubject::class)->sold = $sold;

        $window = $this->take($fields + [Intake::NAME => 'Анна', Intake::MESSAGE => 'Нужен на неделю']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(self::phrase($reason), Intake::FORM === $field ? trim($window->filter('.flash-error')->text()) : self::refusal($window, $field));
        self::assertSame('Анна', $window->filter('input[name="'.Intake::NAME.'"]')->attr('value'));
        self::assertSame('Нужен на неделю', trim($window->filter('textarea[name="'.Intake::MESSAGE.'"]')->text()));
        self::assertSame(0, self::service(LeadRepository::class)->count([]));
    }

    /** @return iterable<string, array{array<string, string>, bool, string, string}> */
    public static function refusals(): iterable
    {
        yield 'no contact' => [[], false, Intake::PHONE, 'leads.refusal.contact'];
        yield 'unknown phone' => [[Intake::PHONE => '12345'], false, Intake::PHONE, 'leads.refusal.phone'];
        yield 'unknown email' => [[Intake::PHONE => '79990000002', Intake::EMAIL => 'anna@'], false, Intake::EMAIL, 'leads.refusal.email'];
        yield 'subject refusal' => [[Intake::PHONE => '79990000002', ItemSubject::FIELD => '1'], true, Intake::FORM, ItemSubject::SOLD];
    }

    #[Group('ACMS-LEAD-0012')]
    #[Group('ACMS-US-0040')]
    public function testFilledTrapIsAcceptedWithoutLead(): void
    {
        $this->take([Intake::PHONE => '79990000002', Intake::TRAP => 'https://spam.test']);

        self::assertResponseHeaderSame('HX-Redirect', self::PAGE);
        self::assertSame(0, self::service(LeadRepository::class)->count([]));
    }

    #[Group('ACMS-LEAD-0012')]
    #[Group('ACMS-US-0040')]
    public function testLeadsOfAddressBeyondLimitAreRefused(): void
    {
        for ($lead = 0; $lead < Intake::LIMIT; ++$lead) {
            $this->take([Intake::PHONE => '79990000002']);
            self::assertResponseHeaderSame('HX-Redirect', self::PAGE);
        }

        $window = $this->take([Intake::PHONE => '79990000002']);

        self::assertSame(self::phrase('leads.refusal.often'), trim($window->filter('.flash-error')->text()));
        self::assertSame(Intake::LIMIT, self::service(LeadRepository::class)->count([]));
    }

    /** @param array<string, string> $fields */
    private function take(array $fields): Crawler
    {
        return $this->client->request(
            'POST',
            self::WINDOW,
            $fields + [CsrfGuard::FIELD => $this->formToken(CsrfGuard::TOKEN_ID), Back::FIELD => self::PAGE],
            server: ['HTTP_HX-Request' => 'true'],
        );
    }

    private static function refusal(Crawler $window, string $field): string
    {
        $label = $window->filter('label')->reduce(static fn (Crawler $label): bool => $label->filter('[name="'.$field.'"]')->count() > 0);

        return trim($label->filter('.field-refusal')->text());
    }
}
