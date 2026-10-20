<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Tests;

use AdzerCMS\Core\Tests\Queries;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Extensions\Entity\Extension;
use AdzerCMS\Extensions\ExtensionType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

final class CatalogAdminTest extends CatalogTestCase
{
    use Queries;

    private const LIST = '/admin/extensions';

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

        self::assertSame(self::phrase($reason, ['name' => 'acme/taken']), trim($dialog->filter('.flash-error')->text()));
        self::assertCount(1, self::extensions()->findAll());
    }

    /** @return iterable<string, array{array<string, string>, string}> */
    public static function refused(): iterable
    {
        yield 'name outside Composer' => [['name' => 'shop'], 'extensions.refusal.name'];
        yield 'name in the catalog' => [['name' => 'acme/taken'], 'extensions.refusal.taken'];
        yield 'unknown type' => [['type' => 'plugin'], 'extensions.refusal.type'];
        yield 'zero price' => [['price' => '0'], 'extensions.refusal.price'];
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

        self::assertSame(['acme/third', 'acme/second', 'acme/first'], $listing->filter('tbody tr strong')->each(static fn (Crawler $name): string => $name->text()));
        self::assertSame(['acme/third', 'acme/second', 'acme/first'], array_map(static fn (Extension $entry): string => $entry->getName(), self::extensions()->ordered()));
    }

    #[Group('ACMS-US-0035')]
    public function testListTakesItsRowsByOneQuery(): void
    {
        $this->assertListingKeepsItsQueries(self::LIST, function (int $from, int $to): void {
            foreach (range($from, $to) as $number) {
                $this->entry('acme/entry-'.$number);
            }
        });
    }
}
