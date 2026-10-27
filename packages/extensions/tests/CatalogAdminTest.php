<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Tests;

use AdzerCMS\Contracts\Module\ExtensionType;
use AdzerCMS\Contracts\Ui\AdminEntry;
use AdzerCMS\Core\Account\LoginNames;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\Delivery;
use AdzerCMS\Core\Tests\Queries;
use AdzerCMS\Core\Ui\Entries;
use AdzerCMS\Core\Ui\Money;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Extensions\Entity\Extension;
use AdzerCMS\Extensions\ExtensionDecision;
use AdzerCMS\Extensions\ExtensionState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

final class CatalogAdminTest extends CatalogTestCase
{
    use Configuration;
    use Delivery;
    use Queries;

    private const LIST = '/admin/extensions';

    protected function setUp(): void
    {
        parent::setUp();
        $this->operate();
    }

    #[Group('ACMS-EXT-0001')]
    #[Group('ACMS-EXT-0002')]
    #[Group('ACMS-US-0035')]
    public function testOperatorAddsEditsAndDropsEntry(): void
    {
        $dialog = $this->client->request('GET', self::LIST.'/new');
        self::assertSame(array_map(static fn (ExtensionType $type): string => $type->value, ExtensionType::cases()),
            $dialog->filter('select[name="type"] option')->each(static fn (Crawler $option): string => (string) $option->attr('value')));

        $this->post(self::LIST.'/new', ['name' => 'Acme/Shop', 'type' => 'theme', 'price' => '1 500,50', 'demo' => 'https://shop.example.test']);
        self::assertResponseHeaderSame('HX-Redirect', self::LIST);

        $entry = self::extensions()->named('acme/shop') ?? self::fail('the entry is not in the catalog');
        self::assertSame(ExtensionType::Theme, $entry->getType());
        self::assertSame('1500.50', $entry->getPrice());
        self::assertSame('https://shop.example.test', $entry->getDemo());

        $this->post(self::LIST.'/'.$entry->getId(), ['name' => 'acme/shop', 'type' => 'module', 'price' => '', 'demo' => '']);
        $entry = $this->reread($entry);
        self::assertSame(ExtensionType::Module, $entry->getType());
        self::assertNull($entry->getPrice());
        self::assertNull($entry->getDemo());

        $listing = $this->fragment(self::LIST);
        self::assertSame(self::phrase('extensions.free'), trim(self::cell($listing, 'acme/shop', self::phrase('extensions.column.price'))->text()));

        $this->post(self::LIST.'/'.$entry->getId().'/drop');
        self::assertResponseRedirects(self::LIST);
        self::assertNull(self::extensions()->named('acme/shop'));
    }

    /** @param array<string, string> $fields */
    #[Group('ACMS-EXT-0001')]
    #[Group('ACMS-US-0035')]
    #[DataProvider('refused')]
    public function testEntryOutsideItsRulesIsRefused(array $fields, string $reason): void
    {
        $this->entry('acme/taken');

        $dialog = $this->post(self::LIST.'/new', $fields + ['name' => 'acme/fresh', 'type' => 'module', 'price' => '', 'demo' => '']);

        self::assertSame(self::phrase($reason, ['name' => 'acme/taken', 'max' => Money::rubles(Extension::PRICE)]), trim($dialog->filter('.flash-error')->text()));
        self::assertCount(1, self::extensions()->findAll());
    }

    /** @return iterable<string, array{array<string, string>, string}> */
    public static function refused(): iterable
    {
        yield 'name outside Composer' => [['name' => 'shop'], 'extensions.refusal.name'];
        yield 'name in the catalog' => [['name' => 'acme/taken'], 'extensions.refusal.taken'];
        yield 'unknown type' => [['type' => 'plugin'], 'extensions.refusal.type'];
        yield 'zero price' => [['price' => '0'], 'extensions.refusal.price'];
        yield 'price beyond its column' => [['price' => '100000000'], 'extensions.refusal.price'];
        yield 'demo outside the web' => [['demo' => 'ftp://shop.example.test'], 'extensions.refusal.demo'];
    }

    #[Group('ACMS-UI-0074')]
    #[Group('ACMS-US-0035')]
    public function testListKeepsOperatorOrder(): void
    {
        $first = $this->entry('acme/first');
        $this->entry('acme/second');
        $third = $this->entry('acme/third');

        $listing = $this->post(self::LIST.'/order', [Order::FIELD => [(string) $third->getId(), (string) $first->getId()]]);

        self::assertSame(['acme/third', 'acme/second', 'acme/first'], self::names($listing));
        self::assertSame(['acme/third', 'acme/second', 'acme/first'], array_map(static fn (Extension $entry): string => $entry->getName(), self::extensions()->ordered()));
    }

    #[Group('ACMS-EXT-0001')]
    #[Group('ACMS-UI-0089')]
    #[Group('ACMS-US-0035')]
    public function testPendingEntriesLeadTheListAndNameTheirAuthor(): void
    {
        $this->entry('acme/first', package: self::package('First'));
        $author = $this->author('79990000002');
        $this->entry('vendor/waiting', author: $author);

        $listing = $this->fragment(self::LIST);

        self::assertSame(['vendor/waiting', 'acme/first'], self::names($listing));
        $cell = self::cell($listing, 'vendor/waiting', self::phrase('extensions.column.author'));
        self::assertSame(self::service(LoginNames::class)->of($author->getUser()), $cell->filter('a strong')->text());
        self::assertSame(self::service(Entries::class)->url(AdminEntry::User, ['id' => $author->getUser()->getId()]), $cell->filter('a')->attr('href'));
    }

    /** @param list<string> $expected */
    #[Group('ACMS-EXT-0004')]
    #[Group('ACMS-EXT-0009')]
    #[Group('ACMS-US-0035')]
    #[DataProvider('states')]
    public function testStateFilterTakesEntriesOfTheState(ExtensionState $state, array $expected): void
    {
        $author = $this->author('79990000002');
        $this->entry('acme/published', package: self::package('Published'));
        $this->entry('acme/missing');
        $this->entry('vendor/pending', package: self::package('Pending', author: $author->getKey()), author: $author);
        $this->entry('vendor/keyed', package: self::package('Keyed', author: $author->getKey()), author: $author)->decide(ExtensionDecision::Published);
        $this->entry('vendor/keyless', package: self::package('Keyless'), author: $author)->decide(ExtensionDecision::Published);
        $this->entry('vendor/foreign', package: self::package('Foreign', author: $this->author('79990000003')->getKey()), author: $author)->decide(ExtensionDecision::Published);
        $this->entry('vendor/rejected', author: $author)->decide(ExtensionDecision::Rejected, 'Not a module');
        $this->entry('vendor/withdrawn', package: self::package('Withdrawn', author: $author->getKey()), author: $author)->decide(ExtensionDecision::Withdrawn, 'Broken demo');
        $this->em->flush();

        self::assertSame($expected, self::names($this->fragment(self::LIST.'?state='.$state->value)));
    }

    /** @return iterable<string, array{ExtensionState, list<string>}> */
    public static function states(): iterable
    {
        yield 'pending' => [ExtensionState::Pending, ['vendor/pending']];
        yield 'published' => [ExtensionState::Published, ['acme/published', 'vendor/keyed']];
        yield 'missing' => [ExtensionState::Missing, ['acme/missing']];
        yield 'unclaimed' => [ExtensionState::Unclaimed, ['vendor/keyless', 'vendor/foreign']];
        yield 'rejected' => [ExtensionState::Rejected, ['vendor/rejected']];
        yield 'withdrawn' => [ExtensionState::Withdrawn, ['vendor/withdrawn']];
    }

    #[Group('ACMS-EXT-0009')]
    #[Group('ACMS-US-0035')]
    #[Group('ACMS-US-0044')]
    #[DataProvider('decisions')]
    public function testOperatorDecisionOnAuthorEntryReachesTheAuthor(ExtensionDecision $from, ExtensionDecision $decision, string $reason): void
    {
        $this->configure(self::MAIL);
        $author = $this->author('79990000002', 'author@example.org');
        $entry = $this->entry('vendor/tool', package: self::package('Tool', author: $author->getKey()), author: $author);
        $entry->decide($from, ExtensionDecision::Pending === $from ? null : 'Earlier');
        $this->em->flush();
        $record = self::LIST.'/'.$entry->getId();

        $this->post($record, ['name' => 'vendor/other', 'type' => 'theme', 'demo' => '']);
        $card = $this->client->request('GET', $record);
        self::assertCount(0, $card->filter('input[name="name"]'), 'the operator does not edit fields of an author entry');
        self::assertSame(array_map(static fn (ExtensionDecision $next): string => $next->value, $from->next()),
            $card->filter('input[name="decision"]')->each(static fn (Crawler $field): string => (string) $field->attr('value')));

        $this->post($record.'/decide', ['decision' => $decision->value, 'reason' => $reason]);

        self::assertResponseHeaderSame('HX-Redirect', self::LIST);
        $entry = $this->reread($entry);
        self::assertSame(['vendor/tool', $decision, '' === $reason ? null : $reason], [$entry->getName(), $entry->getDecision(), $entry->getReason()]);
        self::assertEmailCount(1);
        self::assertStringContainsString('vendor/tool', (string) $this->letter()->getSubject());
        self::assertStringContainsString(self::phrase($entry->state()->label()), $this->letterText());
        self::assertStringContainsString($reason, $this->letterText());
    }

    /** @return iterable<string, array{ExtensionDecision, ExtensionDecision, string}> */
    public static function decisions(): iterable
    {
        yield 'publication of a pending entry' => [ExtensionDecision::Pending, ExtensionDecision::Published, ''];
        yield 'rejection of a pending entry' => [ExtensionDecision::Pending, ExtensionDecision::Rejected, 'No demo'];
        yield 'withdrawal of a published entry' => [ExtensionDecision::Published, ExtensionDecision::Withdrawn, 'Broken demo'];
    }

    #[Group('ACMS-EXT-0009')]
    #[Group('ACMS-US-0035')]
    #[DataProvider('refusedDecisions')]
    public function testDecisionOutsideItsRuleIsRefused(ExtensionDecision $from, string $decision, string $reason, string $refusal): void
    {
        $this->configure(self::MAIL);
        $author = $this->author('79990000002', 'author@example.org');
        $entry = $this->entry('vendor/tool', author: $author);
        $entry->decide($from, ExtensionDecision::Pending === $from ? null : 'Earlier');
        $this->em->flush();

        $card = $this->post(self::LIST.'/'.$entry->getId().'/decide', ['decision' => $decision, 'reason' => $reason]);

        self::assertSame(self::phrase($refusal, ['name' => 'vendor/tool']), trim($card->filter('.flash-error')->text()));
        self::assertSame($from, $this->reread($entry)->getDecision());
        self::assertEmailCount(0);
    }

    /** @return iterable<string, array{ExtensionDecision, string, string, string}> */
    public static function refusedDecisions(): iterable
    {
        yield 'rejection without a reason' => [ExtensionDecision::Pending, 'rejected', '  ', 'extensions.refusal.reason'];
        yield 'decision the state does not lead to' => [ExtensionDecision::Rejected, 'published', '', 'extensions.refusal.decision'];
        yield 'unknown decision' => [ExtensionDecision::Pending, 'approved', '', 'extensions.refusal.decision'];
    }

    #[Group('ACMS-US-0035')]
    public function testListTakesItsRowsByOneQuery(): void
    {
        $this->assertListingKeepsItsQueries(self::LIST, function (int $from, int $to): void {
            foreach (range($from, $to) as $number) {
                $this->entry('acme/entry-'.$number, author: $this->author(\sprintf('7998%07d', $number)));
            }
        });
    }

    /** @return list<string> */
    private static function names(Crawler $listing): array
    {
        return $listing->filter('tbody tr')->each(static fn (Crawler $row): string => $row->filter('strong')->first()->text());
    }
}
