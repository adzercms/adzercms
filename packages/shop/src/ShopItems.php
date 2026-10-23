<?php

declare(strict_types=1);

namespace AdzerCMS\Shop;

use AdzerCMS\Contracts\Ui\Area;
use AdzerCMS\Contracts\Ui\Menu;
use AdzerCMS\Contracts\Ui\MenuItem;

use function Symfony\Component\Translation\t;

final readonly class ShopItems implements Menu
{
    public function items(Area $area): array
    {
        return Area::Admin === $area
            ? [new MenuItem('admin_shop_products', t('shop.menu.products'), 270, ['admin_shop_product', 'admin_shop_categories', 'admin_shop_category'])]
            : [];
    }
}
