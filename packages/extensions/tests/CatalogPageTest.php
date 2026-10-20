<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Tests;

use AdzerCMS\Contracts\Module\Category;
use AdzerCMS\Core\Composer\Package;
use AdzerCMS\Core\Tests\Queries;
use AdzerCMS\Core\Ui\Money;
use AdzerCMS\Extensions\ExtensionType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CatalogPageTest extends CatalogTestCase
{
    use Queries;

    private const PAGE = '/extensions';
    private const SOURCE = 'https://github.com/acme/code';

    #[Group('ACMS-EXT-0004')]
    #[Group('ACMS-EXT-0005')]
    #[Group('ACMS-US-0034')]
    public function testVisitorSeesPublishedEntriesInOperatorOrder(): void
    {
        $this->entry('acme/shop', ExtensionType::Theme, '1500.00', self::package('Shop theme', 'https://shop.example.test', [Category::Commerce, Category::Content]), 'https://demo.example.test');
        $this->entry('acme/draft');
        $this->entry('acme/seo', package: self::package('SEO module'))->place(0);
        $this->em->flush();

        $page = $this->arrive(self::PAGE);

        self::assertResponseIsSuccessful();
        self::assertSame(['acme/seo', 'acme/shop'], self::names($page));

        $shop = $page->filter('.offer-card')->last();
        self::assertSame(
            [self::phrase('extensions.edition', ['type' => self::phrase(ExtensionType::Theme->label()), 'version' => '1.0.0']), self::phrase(Category::Commerce->label()).', '.self::phrase(Category::Content->label())],
            $shop->filter('.muted')->each(static fn (Crawler $line): string => $line->text()),
        );
        self::assertStringContainsString(Money::rubles('1500.00'), $shop->filter('.offer-price')->text());
        self::assertSame('Shop theme', $shop->filter('p')->text());
        self::assertSame(['https://demo.example.test', 'https://shop.example.test', self::SOURCE], self::links($shop));

        $seo = $page->filter('.offer-card')->first();
        self::assertSame(self::phrase('extensions.free'), $seo->filter('.offer-price')->text());
        self::assertSame([self::SOURCE], self::links($seo));
    }

    /**
     * @param array<string, string> $query
     * @param list<string>          $names
     */
    #[Group('ACMS-EXT-0005')]
    #[Group('ACMS-US-0034')]
    #[DataProvider('narrowed')]
    public function testFilterKeepsItsChoiceInAddress(array $query, array $names): void
    {
        $this->entry('acme/theme', ExtensionType::Theme, '900.00', self::package('Theme', categories: [Category::Commerce, Category::Content]));
        $this->entry('acme/module', package: self::package('Module'));

        $list = $this->fragment(self::PAGE.'?'.http_build_query($query));

        self::assertSame($names, self::names($list));
        foreach ($query as $name => $value) {
            self::assertSame($value, $list->filter(\sprintf('select[name="%s"] option[selected]', $name))->attr('value'));
        }
    }

    /** @return iterable<string, array{array<string, string>, list<string>}> */
    public static function narrowed(): iterable
    {
        yield 'type' => [['type' => 'theme'], ['acme/theme']];
        yield 'category' => [['category' => 'content'], ['acme/theme']];
        yield 'free' => [['price' => 'free'], ['acme/module']];
        yield 'paid' => [['price' => 'paid'], ['acme/theme']];
        yield 'type and price apart' => [['type' => 'theme', 'price' => 'free'], []];
    }

    #[Group('ACMS-EXT-0005')]
    public function testPageDescribesItsEntriesAsCollection(): void
    {
        $this->entry('acme/shop', ExtensionType::Theme, '1500.00', self::package('Shop theme', 'https://shop.example.test'));
        $this->entry('acme/seo', package: self::package('SEO module'));

        /** @var array{'@type': string, mainEntity: array{itemListElement: list<array{position: int, item: array{name: string, url: string, offers: array{price: string, priceCurrency: string}}}>}} $schema */
        $schema = json_decode($this->arrive(self::PAGE)->filter('script[type="application/ld+json"]')->text(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('CollectionPage', $schema['@type']);
        self::assertSame(
            [[1, 'acme/shop', 'https://shop.example.test', '1500.00'], [2, 'acme/seo', self::SOURCE, '0']],
            array_map(static fn (array $element): array => [$element['position'], $element['item']['name'], $element['item']['url'], $element['item']['offers']['price']], $schema['mainEntity']['itemListElement']),
        );
        self::assertSame([Money::CURRENCY], array_values(array_unique(array_map(static fn (array $element): string => $element['item']['offers']['priceCurrency'], $schema['mainEntity']['itemListElement']))));
    }

    #[Group('ACMS-UI-0079')]
    public function testHeadCarriesSeoSetWithCanonicalWithoutQuery(): void
    {
        $page = $this->arrive(self::PAGE.'?type=theme');
        $meta = static fn (string $selector, string $attribute = 'content'): ?string => $page->filter($selector)->attr($attribute);

        self::assertSame(self::service(UrlGeneratorInterface::class)->generate('extensions', [], UrlGeneratorInterface::ABSOLUTE_URL), $meta('link[rel="canonical"]', 'href'));
        self::assertSame(
            [$meta('link[rel="canonical"]', 'href'), $page->filter('title')->text(), $meta('meta[name="description"]')],
            [$meta('meta[property="og:url"]'), $meta('meta[property="og:title"]'), $meta('meta[property="og:description"]')],
        );
        self::assertNotSame('', $meta('meta[name="description"]'));
    }

    #[Group('ACMS-US-0034')]
    public function testPageTakesItsEntriesByOneQuery(): void
    {
        $this->assertListingKeepsItsQueries(self::PAGE, function (int $from, int $to): void {
            foreach (range($from, $to) as $number) {
                $this->entry('acme/entry-'.$number, package: self::package('Entry '.$number));
            }
        });
    }

    /** @param list<Category> $categories */
    private static function package(string $description, ?string $homepage = null, array $categories = [Category::Other]): Package
    {
        return new Package($description, '1.0.0', $categories, self::SOURCE, $homepage);
    }

    /** @return list<string> */
    private static function names(Crawler $page): array
    {
        return $page->filter('.offer-title')->each(static fn (Crawler $title): string => $title->text());
    }

    /** @return list<string> */
    private static function links(Crawler $card): array
    {
        return $card->filter('.offer-actions a')->each(static fn (Crawler $link): string => (string) $link->attr('href'));
    }
}
