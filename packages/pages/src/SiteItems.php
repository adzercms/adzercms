<?php

declare(strict_types=1);

namespace AdzerCMS\Pages;

use AdzerCMS\Contracts\Ui\Area;
use AdzerCMS\Contracts\Ui\Menu;
use AdzerCMS\Contracts\Ui\MenuItem;
use AdzerCMS\Pages\Controller\Admin\SiteController;

use function Symfony\Component\Translation\t;

final readonly class SiteItems implements Menu
{
    private const WEIGHT = 550;

    public function items(Area $area): array
    {
        return Area::Admin === $area ? [new MenuItem(SiteController::LIST, t('pages.menu'), self::WEIGHT, [SiteController::PAGE])] : [];
    }
}
