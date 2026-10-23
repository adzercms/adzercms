<?php

declare(strict_types=1);

namespace AdzerCMS\Shop\Tests;

use AdzerCMS\Core\Seo\Indexing;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\Queries;
use AdzerCMS\Core\Ui\Money;
use AdzerCMS\Shop\Cart;
use AdzerCMS\Shop\Entity\Category;
use AdzerCMS\Shop\Entity\Product;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class StorefrontTest extends ShopTestCase
{
    use Configuration;
    use Queries;

    private const SHOP = '/shop';
    private const CART = '/shop/cart';
    private const CHANGE = '/shop/cart/line';

    private Category $mugs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mugs = $this->category('Кружки');
    }

    #[Group('ACMS-SHOP-0007')]
    #[Group('ACMS-US-0042')]
    public function testVisitorSeesActiveProductsByCategories(): void
    {
        $cups = $this->category('Чашки', 2);
        $this->category('Блюдца', 3);
        $tall = $this->product($this->mugs, 'Высокая', null, ['250 мл' => '450', '400 мл' => '590.50'], position: 2);
        $low = $this->product($this->mugs, 'Низкая', '300.50', position: 1);
        $low->show(self::file('a'));
        $this->em->flush();
        $this->product($this->mugs, 'Снятая', '100', active: false, position: 3);
        $this->product($cups, 'Чашка', '200');

        $page = $this->arrive(self::SHOP);

        self::assertSame(
            [['Кружки', '#category-'.$this->mugs->getId()], ['Чашки', '#category-'.$cups->getId()]],
            $page->filter('nav.tabs a')->each(static fn (Crawler $link): array => [$link->text(), $link->attr('href')]),
        );
        self::assertSame(
            [['category-'.$this->mugs->getId(), 'Кружки', ['Низкая', 'Высокая']], ['category-'.$cups->getId(), 'Чашки', ['Чашка']]],
            $page->filter('section.section')->each(static fn (Crawler $group): array => [$group->attr('id'), $group->filter('h2')->text(), $group->filter('.offer-title')->each(static fn (Crawler $title): string => $title->text())]),
        );

        $card = $page->filter('#product-'.$low->getId());
        self::assertSame('/shop/photo/'.self::file('a'), $card->filter('img')->attr('src'));
        self::assertSame(self::sum('300.50'), $card->filter('.offer-price')->text());
        self::assertSame((string) $low->getId(), $card->filter('form input[name="product"]')->attr('value'));

        $card = $page->filter('#product-'.$tall->getId());
        self::assertSame(self::phrase('shop.price.from')."\u{a0}".self::sum('450'), $card->filter('.offer-price')->text());
        self::assertCount(0, $card->filter('form'));
        self::assertSame('/shop/'.$tall->getId(), $card->filter('.offer-actions a')->attr('href'));
    }

    #[Group('ACMS-SHOP-0008')]
    #[Group('ACMS-US-0042')]
    public function testProductPageOffersVariantsWithPrices(): void
    {
        $mug = $this->product($this->mugs, 'Кружка', null, ['250 мл' => '450', '400 мл' => '590.50']);
        $mug->change($this->mugs, 'Кружка', 'Фарфор', true);
        $mug->show(self::file('a'));
        $mug->show(self::file('b'));
        $this->em->flush();

        $page = $this->arrive('/shop/'.$mug->getId());

        self::assertSame('Кружка', $page->filter('h1')->text());
        self::assertSame(['/shop/photo/'.self::file('a'), '/shop/photo/'.self::file('b')], $page->filter('.photos img')->each(static fn (Crawler $photo): ?string => $photo->attr('src')));
        self::assertSame('Фарфор', $page->filter('.page-lead')->text());
        [$small, $large] = $mug->getVariants();
        self::assertSame(
            [[(string) $small->getId(), '250 мл', self::sum('450'), true], [(string) $large->getId(), '400 мл', self::sum('590.50'), false]],
            $page->filter('fieldset.choices label')->each(static fn (Crawler $choice): array => [
                $choice->filter('input')->attr('value'),
                $choice->filter('span')->first()->text(),
                $choice->filter('.muted')->text(),
                null !== $choice->filter('input')->attr('checked'),
            ]),
        );
        self::assertSame('1', $page->filter('input[name="quantity"]')->attr('value'));
    }

    #[Group('ACMS-SHOP-0008')]
    public function testHiddenOrMissingProductHasNoPage(): void
    {
        $hidden = $this->product($this->mugs, 'Снятая', '100', active: false);

        foreach ([$hidden->getId(), (int) $hidden->getId() + 1] as $id) {
            $this->client->request('GET', '/shop/'.$id);
            self::assertResponseStatusCodeSame(404);
        }
    }

    #[Group('ACMS-SHOP-0009')]
    #[Group('ACMS-SHOP-0010')]
    #[Group('ACMS-US-0042')]
    public function testAddingSumsSameLineUpToLimit(): void
    {
        $low = $this->product($this->mugs, 'Низкая', '300');
        $tall = $this->product($this->mugs, 'Высокая', null, ['250 мл' => '450', '400 мл' => '590.50']);
        $large = $tall->getVariants()[1];

        $this->post(self::CART, ['product' => (string) $low->getId(), 'card' => '1', 'back' => self::SHOP]);
        self::assertResponseRedirects(self::SHOP.'#product-'.$low->getId(), 303);
        $page = $this->client->followRedirect();
        self::assertSame(self::phrase('shop.flash.added', ['name' => 'Низкая']), self::toast($page, 'success'));
        self::assertSame(self::phrase('shop.cart.link', ['count' => 1]), $page->filter('.card-actions a')->text());

        $this->post(self::CART, ['product' => (string) $low->getId(), 'quantity' => '2', 'back' => '/shop/'.$low->getId()]);
        self::assertResponseRedirects('/shop/'.$low->getId(), 303);
        $this->post(self::CART, ['product' => (string) $tall->getId(), 'variant' => (string) $large->getId(), 'quantity' => '3']);
        self::assertResponseRedirects(self::SHOP, 303);

        self::assertSame([['Низкая', '', self::sum('300'), '3', self::sum('900')], ['Высокая', '400 мл', self::sum('590.50'), '3', self::sum('1771.50')]], $this->lines());
        self::assertSame(self::sum('2671.50'), $this->arrive(self::CART)->filter('tfoot th')->eq(1)->text());

        $this->post(self::CART, ['product' => (string) $low->getId(), 'quantity' => (string) Cart::LIMIT]);
        self::assertSame((string) Cart::LIMIT, $this->lines()[0][3]);
        self::assertSame(self::phrase('shop.cart.link', ['count' => Cart::LIMIT + 3]), $this->arrive(self::SHOP)->filter('.card-actions a')->text());
    }

    #[Group('ACMS-SHOP-0009')]
    #[Group('ACMS-US-0042')]
    public function testChangingSetsQuantityAndZeroRemovesLine(): void
    {
        $low = $this->product($this->mugs, 'Низкая', '300');
        $high = $this->product($this->mugs, 'Высокая', '500');
        $this->post(self::CART, ['product' => (string) $low->getId()]);
        $this->post(self::CART, ['product' => (string) $high->getId()]);
        $this->arrive(self::CART);

        $this->post(self::CHANGE, ['line' => (string) $low->getId(), 'quantity' => '5', 'back' => self::CART]);
        self::assertResponseRedirects(self::CART, 303);
        self::assertSame(self::phrase('shop.flash.changed', ['name' => 'Низкая', 'quantity' => 5]), self::toast($this->client->followRedirect(), 'success'));

        $this->post(self::CHANGE, ['line' => (string) $high->getId(), 'quantity' => '0']);
        self::assertSame(self::phrase('shop.flash.removed', ['name' => 'Высокая']), self::toast($this->client->followRedirect(), 'success'));
        self::assertSame([['Низкая', '', self::sum('300'), '5', self::sum('1500')]], $this->lines());
    }

    #[Group('ACMS-SHOP-0009')]
    #[Group('ACMS-US-0042')]
    #[DataProvider('refusedAdditions')]
    public function testAdditionIsRefusedWithReason(string $product, bool $variant, string $quantity, string $reason): void
    {
        $products = [
            'hidden' => $this->product($this->mugs, 'Снятая', '100', active: false),
            'plain' => $this->product($this->mugs, 'Низкая', '300'),
            'varied' => $this->product($this->mugs, 'Высокая', null, ['250 мл' => '450']),
        ];
        $chosen = $products[$product];

        $this->post(self::CART, ['product' => (string) $chosen->getId(), 'quantity' => $quantity, 'back' => self::SHOP] + ($variant ? ['variant' => (string) $chosen->getVariants()[0]->getId()] : []));

        self::assertSame(self::phrase($reason, ['limit' => Cart::LIMIT]), self::toast($this->client->followRedirect(), 'error'));
        self::assertSame([], $this->lines());
    }

    /** @return iterable<string, array{string, bool, string, string}> */
    public static function refusedAdditions(): iterable
    {
        yield 'hidden product' => ['hidden', false, '1', 'shop.refusal.gone'];
        yield 'variant not chosen' => ['varied', false, '1', 'shop.refusal.choose'];
        yield 'zero' => ['plain', false, '0', 'shop.refusal.quantity'];
        yield 'over the limit' => ['varied', true, (string) (Cart::LIMIT + 1), 'shop.refusal.quantity'];
        yield 'not a number' => ['plain', false, '1.5', 'shop.refusal.quantity'];
    }

    #[Group('ACMS-SHOP-0009')]
    #[Group('ACMS-US-0042')]
    public function testReadingDropsLinesOfWithdrawnProductsAndVariants(): void
    {
        $low = $this->product($this->mugs, 'Низкая', '300');
        $plain = $this->product($this->mugs, 'Простая', '200');
        $tall = $this->product($this->mugs, 'Высокая', null, ['250 мл' => '450', '400 мл' => '590.50']);
        [$small, $large] = $tall->getVariants();
        $this->post(self::CART, ['product' => (string) $low->getId()]);
        $this->post(self::CART, ['product' => (string) $plain->getId()]);
        $this->post(self::CART, ['product' => (string) $tall->getId(), 'variant' => (string) $small->getId(), 'quantity' => '2']);
        $this->post(self::CART, ['product' => (string) $tall->getId(), 'variant' => (string) $large->getId()]);

        $this->em->clear();
        $this->fresh($low)->change($this->fresh($low)->getCategory(), 'Низкая', null, false);
        $this->fresh($plain)->vary(null, [['id' => null, 'name' => 'S', 'price' => '200']]);
        $this->fresh($tall)->vary(null, [['id' => $small->getId(), 'name' => '250 мл', 'price' => '450']]);
        $this->em->flush();

        self::assertSame([['Высокая', '250 мл', self::sum('450'), '2', self::sum('900')]], $this->lines());
        self::assertSame(self::phrase('shop.cart.link', ['count' => 2]), $this->arrive(self::SHOP)->filter('.card-actions a')->text());
    }

    #[Group('ACMS-SHOP-0007')]
    #[Group('ACMS-SHOP-0008')]
    public function testPagesDescribeProductsForSearch(): void
    {
        $low = $this->product($this->mugs, 'Низкая', '300');
        $tall = $this->product($this->mugs, 'Высокая', null, ['250 мл' => '450', '400 мл' => '590.50'], position: 2);
        $tall->show(self::file('a'));
        $this->em->flush();

        $shop = self::schema($this->arrive(self::SHOP));
        self::assertSame('CollectionPage', $shop['@type']);
        self::assertSame(
            [[1, 'Низкая', self::absolute('shop_product', ['id' => $low->getId()])], [2, 'Высокая', self::absolute('shop_product', ['id' => $tall->getId()])]],
            array_map(static fn (array $element): array => [$element['position'], $element['name'], $element['url']], $shop['mainEntity']['itemListElement']),
        );

        $product = self::schema($this->arrive('/shop/'.$tall->getId()));
        self::assertSame(['Product', 'Высокая', 'Кружки', [self::absolute('shop_photo', ['name' => self::file('a')])]], [$product['@type'], $product['name'], $product['category'], $product['image']]);
        self::assertSame(
            [['250 мл', '450.00', Money::CURRENCY], ['400 мл', '590.50', Money::CURRENCY]],
            array_map(static fn (array $offer): array => [$offer['name'], $offer['price'], $offer['priceCurrency']], $product['offers']),
        );
        $single = self::schema($this->arrive('/shop/'.$low->getId()))['offers'];
        self::assertSame(['Offer', '300.00'], [$single['@type'], $single['price']]);
    }

    #[Group('ACMS-SHOP-0008')]
    public function testSitemapListsActiveProductPages(): void
    {
        $this->configure([Indexing::NAME => '1']);
        $shown = $this->product($this->mugs, 'Низкая', '300');
        $this->product($this->mugs, 'Снятая', '100', active: false);

        $this->client->request('GET', '/sitemap.xml');
        $sitemap = new \SimpleXMLElement((string) $this->client->getResponse()->getContent());
        $sitemap->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $shop = array_values(array_filter(array_map(strval(...), $sitemap->xpath('//s:loc') ?: []), static fn (string $loc): bool => str_starts_with($loc, self::absolute('shop'))));

        self::assertSame([self::absolute('shop'), self::absolute('shop_product', ['id' => $shown->getId()])], $shop);
        self::assertNotSame([], $sitemap->xpath(\sprintf('//s:url[s:loc="%s"]/s:lastmod', self::absolute('shop_product', ['id' => $shown->getId()]))));
        $this->client->request('GET', '/robots.txt');
        self::assertStringNotContainsString('Disallow: /shop/photo', (string) $this->client->getResponse()->getContent());
    }

    #[Group('ACMS-US-0042')]
    public function testShopTakesItsProductsByOneQuery(): void
    {
        $this->assertListingKeepsItsQueries(self::SHOP, function (int $from, int $to): void {
            $mugs = $this->em->find(Category::class, $this->mugs->getId()) ?? self::fail('the category is gone');
            foreach (range($from, $to) as $number) {
                $product = $this->product($mugs, 'Кружка '.$number, null, ['S' => '100', 'L' => '200'], position: $number);
                $product->show(self::file((string) $number));
            }
            $this->em->flush();
        });
    }

    private function fresh(Product $product): Product
    {
        return $this->em->find(Product::class, $product->getId()) ?? self::fail('the product is gone');
    }

    /** @return list<array{string, string, string, string, string}> */
    private function lines(): array
    {
        return $this->arrive(self::CART)->filter('tbody tr')->each(static fn (Crawler $line): array => [
            $line->filter('td a')->text(),
            $line->filter('td .muted')->text(''),
            $line->filter('td')->eq(1)->text(),
            (string) $line->filter('input[name="quantity"][type="number"]')->attr('value'),
            $line->filter('td')->eq(3)->text(),
        ]);
    }

    private static function sum(string $amount): string
    {
        return self::phrase(Money::precise($amount));
    }

    /** @return array<string, mixed> */
    private static function schema(Crawler $page): array
    {
        /* @var array<string, mixed> */
        return json_decode($page->filter('script[type="application/ld+json"]')->text(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /** @param array<string, int|string|null> $parameters */
    private static function absolute(string $route, array $parameters = []): string
    {
        return self::service(UrlGeneratorInterface::class)->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private static function file(string $seed): string
    {
        return md5($seed).'.webp';
    }
}
