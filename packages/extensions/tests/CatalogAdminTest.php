<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Tests;

use AdzerCMS\Contracts\Module\ExtensionType;
use AdzerCMS\Contracts\Ui\AdminEntry;
use AdzerCMS\Core\Account\LoginNames;
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
