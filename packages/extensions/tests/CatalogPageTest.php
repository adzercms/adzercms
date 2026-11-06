<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Tests;

use AdzerCMS\Contracts\Module\Category;
use AdzerCMS\Contracts\Module\ExtensionType;
use AdzerCMS\Core\Seo\Indexing;
use AdzerCMS\Core\Setting\Brand;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\Queries;
use AdzerCMS\Core\Ui\Money;
use AdzerCMS\Extensions\Controller\CatalogController;
use AdzerCMS\Extensions\ExtensionDecision;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CatalogPageTest extends CatalogTestCase
{
    use Configuration;
    use Queries;

    private const PAGE = '/extensions';
    private const STORE = '/extensions/store.json';
    private const GUIDE = __DIR__.'/../docs/';

    #[Group('ACMS-EXT-0003')]
    #[Group('ACMS-EXT-0004')]
    #[Group('ACMS-EXT-0005')]
    #[Group('ACMS-US-0034')]
    public function testVisitorSeesPublishedEntriesInOperatorOrder(): void
    {
        $cover = $this->pictured($this->entry('acme/shop', ExtensionType::Theme, '1500.00', self::package('Shop theme', 'https://shop.example.test', [Category::Commerce, Category::Content], ['ru' => 'Тема магазина'], title: ['en' => 'Shop', 'ru' => 'Магазин']), 'https://demo.example.test'), 'front.png', 'back.png')->getCover();
        $this->entry('acme/draft');
        $this->entry('acme/seo', package: self::package('SEO module'))->place(0);
        $this->em->flush();

        $page = $this->arrive(self::PAGE);
        self::assertSame([null, '/extensions/images/'.$cover], $page->filter('.listing-cover')->each(static fn (Crawler $cover): ?string => $cover->filter('img')->count() > 0 ? $cover->filter('img')->attr('src') : null),
            'the cover is the first image of the entry, an entry without images has none');
        $this->client->request('GET', '/extensions/images/'.$cover);
        self::assertResponseHeaderSame('Content-Type', 'image/webp');

        self::assertResponseIsSuccessful();
        self::assertSame(['acme/seo', 'acme/shop'], self::names($page));

        $shop = $page->filter('.listing-row')->last();
        self::assertSame(
            [
                [self::phrase('extensions.fact.section'), self::phrase(ExtensionType::Theme->label())],
                [self::phrase('extensions.fact.category'), self::phrase(Category::Commerce->label()).', '.self::phrase(Category::Content->label())],
                [self::phrase('extensions.column.version'), '1.0.0'],
            ],
            array_map(null, $shop->filter('.listing-facts dt')->each(static fn (Crawler $term): string => $term->text()), $shop->filter('.listing-facts dd')->each(static fn (Crawler $value): string => $value->text())),
        );
        self::assertSame(self::phrase(Money::rubles('1500.00')), $shop->filter('.listing-price')->text());
        self::assertSame(['https://demo.example.test', 'https://shop.example.test', self::SOURCE], self::links($shop));

        $seo = $page->filter('.listing-row')->first();
        self::assertSame(self::phrase('extensions.free'), $seo->filter('.listing-price')->text());
        self::assertSame([self::SOURCE], self::links($seo));

        $texts = static fn (Crawler $rows): array => $rows->each(static fn (Crawler $row): array => [$row->filter('.listing-title')->text(), $row->filter('p')->text()]);
        self::assertSame([['acme/seo', 'SEO module'], ['Shop', 'Shop theme']], $texts($page->filter('.listing-row')));
        self::assertSame([['acme/seo', 'SEO module'], ['Магазин', 'Тема магазина']], $texts($this->client->request('GET', self::PAGE, server: ['HTTP_ACCEPT_LANGUAGE' => 'ru'])->filter('.listing-row')),
            'the title and description go in the language of the visitor, without a translation — in the source, without a title — the package name');
    }

    #[Group('ACMS-EXT-0004')]
    #[Group('ACMS-EXT-0010')]
    #[Group('ACMS-US-0034')]
    public function testAuthorEntryGoesOnSitePublishedWithKeyOfItsAuthor(): void
    {
        $author = $this->author('79990000002');
        $this->entry('acme/waiting', package: self::package('Waiting', author: $author->getKey()), author: $author);
        $this->entry('acme/keyed', package: self::package('Keyed', author: $author->getKey()), author: $author)->decide(ExtensionDecision::Published);
        $this->entry('acme/foreign', package: self::package('Foreign', author: $this->author('79990000003')->getKey()), author: $author)->decide(ExtensionDecision::Published);
        $this->em->flush();

        self::assertSame(['acme/keyed'], self::names($this->arrive(self::PAGE)));
        $this->client->request('GET', self::STORE);
        self::assertSame(['acme/keyed'], array_column(json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR)['extensions'], 'name'));
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
            self::assertSame($value, $list->filter(\sprintf('form input[name="%1$s"], select[name="%1$s"] option[selected]', $name))->attr('value'));
        }
    }

    #[Group('ACMS-UI-0080')]
    #[Group('ACMS-US-0034')]
    public function testTypeTabsKeepOtherFilters(): void
    {
        $tabs = [['', self::phrase(ExtensionType::all())]];
        foreach (ExtensionType::cases() as $type) {
            $tabs[] = [$type->value, self::phrase($type->tab())];
        }

        $list = $this->fragment(self::PAGE.'?type=theme&price=paid&page=2');

        self::assertSame(
            array_map(static fn (array $tab): array => [$tab[1], self::PAGE.'?'.http_build_query(array_filter(['type' => $tab[0], 'price' => 'paid'])), 'theme' === $tab[0]], $tabs),
            $list->filter('.tabs .tab')->each(static fn (Crawler $tab): array => [$tab->text(), $tab->attr('href'), str_contains((string) $tab->attr('class'), 'active')]),
        );
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
        $cover = $this->pictured($this->entry('acme/shop', ExtensionType::Theme, '1500.00', self::package('Shop theme', 'https://shop.example.test')), 'front.png')->getCover();
        $this->entry('acme/seo', package: self::package('SEO module'));

        /** @var array{'@type': string, mainEntity: array{itemListElement: list<array{position: int, item: array{name: string, url: string, image?: string, offers: array{price: string, priceCurrency: string}}}>}} $schema */
        $schema = json_decode($this->arrive(self::PAGE)->filter('script[type="application/ld+json"]')->text(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('CollectionPage', $schema['@type']);
        self::assertSame(
            [[1, 'acme/shop', 'https://shop.example.test', self::image($cover), '1500.00'], [2, 'acme/seo', self::SOURCE, null, '0']],
            array_map(static fn (array $element): array => [$element['position'], $element['item']['name'], $element['item']['url'], $element['item']['image'] ?? null, $element['item']['offers']['price']], $schema['mainEntity']['itemListElement']),
        );
        self::assertSame([Money::CURRENCY], array_values(array_unique(array_map(static fn (array $element): string => $element['item']['offers']['priceCurrency'], $schema['mainEntity']['itemListElement']))));
    }

    #[Group('ACMS-EXT-0007')]
    public function testStoreListsPublishedEntriesInOrderUntilChanged(): void
    {
        $images = $this->pictured($this->entry('acme/shop', ExtensionType::Theme, '1500.00', self::package('Shop theme'), 'https://demo.example.test'), 'front.png', 'back.png')->getImages();
        $this->entry('acme/draft');
        $this->entry('acme/seo', package: self::package('SEO module'))->place(0);
        $this->em->flush();

        $this->client->request('GET', self::STORE);
        $response = $this->client->getResponse();

        self::assertResponseIsSuccessful();
        self::assertSame(['extensions' => [
            ['name' => 'acme/seo', 'type' => 'module', 'demo' => null, 'images' => []],
            ['name' => 'acme/shop', 'type' => 'theme', 'demo' => 'https://demo.example.test', 'images' => array_map(self::image(...), $images)],
        ]], json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR));

        $this->client->request('GET', self::STORE, server: ['HTTP_IF_NONE_MATCH' => $response->getEtag()]);
        self::assertResponseStatusCodeSame(304);

        $this->entry('acme/forms', package: self::package('Forms module'));
        $this->client->request('GET', self::STORE, server: ['HTTP_IF_NONE_MATCH' => $response->getEtag()]);
        self::assertResponseIsSuccessful();
    }

    #[Group('ACMS-EXT-0005')]
    public function testSitemapDatesPageByItsEntries(): void
    {
        $this->configure([Indexing::NAME => '1']);
        $this->entry('acme/seo', package: self::package('SEO module'));

        $this->client->request('GET', '/sitemap.xml');
        $sitemap = new \SimpleXMLElement((string) $this->client->getResponse()->getContent());
        $sitemap->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        self::assertCount(1, $sitemap->xpath(\sprintf('//s:url[s:loc="%s"]/s:lastmod', self::service(UrlGeneratorInterface::class)->generate('extensions', [], UrlGeneratorInterface::ABSOLUTE_URL))) ?: []);
    }

    #[Group('ACMS-UI-0122')]
    public function testHeadCarriesSeoSetWithCanonicalWithoutQuery(): void
    {
        $logo = self::parameter('adzercms.files_dir').'/upload/logo.svg';
        (new Filesystem())->dumpFile($logo, '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="10"><rect width="40" height="10"/></svg>');
        self::command('adzercms:setting')->execute(['name' => Brand::LOGO, 'value' => $logo]);
        $page = $this->arrive(self::PAGE.'?type=theme');
        $meta = static fn (string $selector, string $attribute = 'content'): ?string => $page->filter($selector)->attr($attribute);

        self::assertSame(self::service(UrlGeneratorInterface::class)->generate('extensions', [], UrlGeneratorInterface::ABSOLUTE_URL), $meta('link[rel="canonical"]', 'href'));
        self::assertSame(
            [$meta('link[rel="canonical"]', 'href'), $page->filter('title')->text(), $meta('meta[name="description"]')],
            [$meta('meta[property="og:url"]'), $meta('meta[property="og:title"]'), $meta('meta[property="og:description"]')],
        );
        self::assertNotSame('', $meta('meta[name="description"]'));
        self::assertSame('http://localhost/brand.png', $meta('meta[property="og:image"]'));
    }

    #[Group('ACMS-EXT-0021')]
    #[Group('ACMS-US-0044')]
    #[DataProvider('guided')]
    public function testGuideOpensFromCatalogAndMyExtensionsInReaderLanguage(string $entry, bool $signed, string $language, string $file): void
    {
        if ($signed) {
            $this->client->loginUser($this->record('79990000002'));
        }
        $this->client->setServerParameter('HTTP_ACCEPT_LANGUAGE', $language);

        $page = $this->client->request('GET', $entry);
        $guide = $this->client->click($page->selectLink(self::phrase('extensions.guide.link', locale: $language))->link());

        self::assertResponseIsSuccessful();
        self::assertSame([substr((string) strtok((string) file_get_contents(self::GUIDE.$file), "\n"), 2)], $guide->filter('h1')->each(static fn (Crawler $title): string => $title->text()));
    }

    /** @return iterable<string, array{string, bool, string, string}> */
    public static function guided(): iterable
    {
        yield 'catalog in English' => [self::PAGE, false, 'en', 'publishing.md'];
        yield 'catalog in Russian' => [self::PAGE, false, 'ru', 'publishing.ru.md'];
        yield 'my extensions' => ['/extensions/mine', true, 'en', 'publishing.md'];
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

    /** @return list<string> */
    private static function names(Crawler $page): array
    {
        return $page->filter('.listing-row')->each(static fn (Crawler $row): string => (string) $row->attr('data-package'));
    }

    /** @return list<string> */
    private static function links(Crawler $row): array
    {
        return $row->filter('.listing-links a')->each(static fn (Crawler $link): string => (string) $link->attr('href'));
    }

    private static function image(?string $name): string
    {
        return self::service(UrlGeneratorInterface::class)->generate(CatalogController::IMAGE, ['file' => $name], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
