<?php

declare(strict_types=1);

namespace AdzerCMS\Shop;

use AdzerCMS\Contracts\Seo\Page;
use AdzerCMS\Contracts\Seo\Pages;
use AdzerCMS\Shop\Controller\PhotoController;
use AdzerCMS\Shop\Controller\StorefrontController;
use AdzerCMS\Shop\Entity\Category;
use AdzerCMS\Shop\Entity\Photo;
use AdzerCMS\Shop\Entity\Product;
use AdzerCMS\Shop\Entity\Variant;

use function Symfony\Component\Translation\t;

final readonly class ShopPages implements Pages
{
    private const SOURCES = [Product::class, Variant::class, Photo::class];

    public function __construct(private Products $products)
    {
    }

    public function pages(): array
    {
        $names = $this->products->names();

        return [
            new Page(StorefrontController::PAGE, t('shop.storefront.title'), [PhotoController::ROUTE], [...self::SOURCES, Category::class]),
            ...array_map(
                static fn (int $id, string $name): Page => new Page(StorefrontController::PRODUCT, t('shop.page.product', ['name' => $name]), sources: self::SOURCES, parameters: ['id' => $id]),
                array_keys($names),
                $names,
            ),
        ];
    }
}
