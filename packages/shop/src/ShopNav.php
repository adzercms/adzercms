<?php

declare(strict_types=1);

namespace AdzerCMS\Shop;

use AdzerCMS\Contracts\Ui\MenuItem;
use AdzerCMS\Contracts\Ui\SiteSections;
use AdzerCMS\Shop\Controller\CheckoutController;
use AdzerCMS\Shop\Controller\StorefrontController;

use function Symfony\Component\Translation\t;

final readonly class ShopNav implements SiteSections
{
    private const WEIGHT = 100;

    public function __construct(private Cart $cart)
    {
    }

    public function sections(): array
    {
        return [
            new MenuItem(StorefrontController::PAGE, t('shop.storefront.title'), self::WEIGHT, [StorefrontController::PRODUCT]),
            new MenuItem(CheckoutController::CART, t('shop.cart.link', ['count' => $this->cart->count()]), self::WEIGHT + 1),
        ];
    }
}
