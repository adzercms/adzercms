<?php

declare(strict_types=1);

namespace AdzerCMS\Shop\Tests;

use AdzerCMS\Core\Ui\Money;
use AdzerCMS\Shop\Entity\Category;
use AdzerCMS\Shop\Entity\Product;
use AdzerCMS\Shop\Photos;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

final class ProductAdminTest extends ShopTestCase
{
    private const LIST = '/admin/shop/products';
    private const CATEGORIES = '/admin/shop/categories';

    private Category $mugs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signInOwner();
        $this->mugs = $this->category('Кружки');
    }

    #[Group('ACMS-SHOP-0003')]
    #[Group('ACMS-SHOP-0004')]
    #[Group('ACMS-US-0043')]
    public function testOwnerKeepsProductWithPriceOrVariants(): void
    {
        $this->post(self::LIST.'/new', $this->fields(['variants' => [
            ['name' => '250 мл', 'price' => '450'],
            ['name' => '', 'price' => ''],
            ['name' => '400 мл', 'price' => '590,50'],
        ]]));
        self::assertResponseHeaderSame('HX-Redirect', self::LIST);
        $product = $this->only();
        self::assertNull($product->getPrice());
        self::assertSame([['250 мл', '450.00'], ['400 мл', '590.50']], self::variants($product));

        $listing = $this->client->request('GET', self::LIST);
        self::assertSame(self::phrase('shop.price.from')."\u{a0}".self::phrase(Money::rubles('450')), trim(self::cell($listing, 'Кружка', self::phrase('shop.column.price'))->text()));

        $kept = $product->getVariants()[1];
        $this->post(self::LIST.'/'.$product->getId(), $this->fields(['variants' => [
            ['id' => (string) $kept->getId(), 'name' => '400 мл', 'price' => '600'],
        ]]));
        $product = $this->only();
        self::assertSame([['400 мл', '600.00']], self::variants($product));
        self::assertSame($kept->getId(), $product->getVariants()[0]->getId(), 'a kept variant keeps its id for the carts holding it');

        $this->post(self::LIST.'/'.$product->getId(), $this->fields(['price' => '500', 'variants' => []]));
        $product = $this->only();
        self::assertSame('500.00', $product->getPrice());
        self::assertSame([], self::variants($product));
        self::assertFalse($product->isPriceFrom());
    }

    /** @param array<string, mixed> $override */
    #[DataProvider('refusals')]
    #[Group('ACMS-SHOP-0003')]
    #[Group('ACMS-US-0043')]
    public function testRefusalStaysInDialog(array $override, string $reason): void
    {
        $dialog = $this->post(self::LIST.'/new', $this->fields($override));

        self::assertSame(self::phrase($reason, ['limit' => 64]), trim($dialog->filter('.flash-error')->text()));
        self::assertSame(0, $this->em->getRepository(Product::class)->count());
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function refusals(): iterable
    {
        yield 'without price and variants' => [['price' => ''], 'shop.refusal.unpriced'];
        yield 'negative price' => [['price' => '-5'], 'shop.refusal.price'];
        yield 'variant without price' => [['price' => '', 'variants' => [['name' => 'XL', 'price' => '']]], 'shop.refusal.variant'];
        yield 'variant without name' => [['price' => '', 'variants' => [['name' => '', 'price' => '100']]], 'shop.refusal.variant'];
    }

    #[Group('ACMS-SHOP-0005')]
    #[Group('ACMS-CORE-0983')]
    #[Group('ACMS-US-0043')]
    public function testPhotosAreWebpInUploadOrderAndLeaveWithProduct(): void
    {
        $this->post(self::LIST.'/new', $this->fields(), ['photos' => [$this->picture('front.png'), $this->picture('side.png')]]);
        $product = $this->only();
        $photos = $product->getPhotos();
        self::assertCount(2, $photos);
        self::assertSame($photos[0]->getFile(), $product->photo());
        $paths = array_map(fn ($photo): string => (string) self::service(Photos::class)->path($photo->getFile()), $photos);
        self::assertSame(['image/webp', 'image/webp'], array_map(mime_content_type(...), $paths));

        $this->client->request('GET', '/shop/photo/'.$photos[0]->getFile());
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Cache-Control', 'immutable, max-age=31536000, public');
        $this->client->request('GET', '/shop/photo/'.$photos[0]->getFile(), server: ['HTTP_IF_NONE_MATCH' => (string) $this->client->getResponse()->headers->get('ETag')]);
        self::assertResponseStatusCodeSame(304);

        $this->post(self::LIST.'/'.$product->getId(), $this->fields(['hidden' => [(string) $photos[0]->getId()]]));
        self::assertFileDoesNotExist($paths[0]);
        self::assertSame($photos[1]->getFile(), $this->only()->photo());

        $this->post(self::LIST.'/'.$product->getId().'/drop');
        self::assertFileDoesNotExist($paths[1]);
        self::assertSame(0, $this->em->getRepository(Product::class)->count());
    }

    #[Group('ACMS-SHOP-0003')]
    #[Group('ACMS-US-0043')]
    public function testProductMovedToAnotherCategoryGoesLast(): void
    {
        $plates = $this->category('Тарелки', 2);
        $this->product($plates, 'Тарелка глубокая', '700', position: 1);
        $this->product($plates, 'Тарелка плоская', '650', position: 2);
        $mug = $this->product($this->mugs, 'Кружка', '450');

        $this->post(self::LIST.'/'.$mug->getId(), $this->fields(['category' => (string) $plates->getId()]));

        $names = $this->client->request('GET', self::LIST)->filter('tbody td:nth-child(3) strong')->each(static fn ($name): string => $name->text());
        self::assertSame(['Тарелка глубокая', 'Тарелка плоская', 'Кружка'], $names);
    }

    #[Group('ACMS-SHOP-0002')]
    #[Group('ACMS-US-0043')]
    public function testCategoryWithProductsIsKept(): void
    {
        $this->product($this->mugs, 'Кружка', '450');

        $this->post(self::CATEGORIES.'/'.$this->mugs->getId().'/drop');

        self::assertResponseRedirects(self::CATEGORIES);
        self::assertSame(self::phrase('shop.refusal.category_filled', ['name' => 'Кружки']), self::toast($this->client->followRedirect(), 'error'));
        self::assertSame(1, $this->em->getRepository(Category::class)->count());
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function fields(array $override = []): array
    {
        return $override + [
            'category' => (string) $this->mugs->getId(),
            'name' => 'Кружка',
            'description' => 'Керамика ручной работы',
            'price' => '450',
            'variants' => [],
            'active' => '1',
        ];
    }

    private function only(): Product
    {
        $this->em->clear();
        $products = $this->em->getRepository(Product::class)->findAll();
        self::assertCount(1, $products);

        return $products[0];
    }

    /** @return list<array{string, string}> */
    private static function variants(Product $product): array
    {
        return array_map(static fn ($variant): array => [$variant->getName(), $variant->getPrice()], $product->getVariants());
    }
}
