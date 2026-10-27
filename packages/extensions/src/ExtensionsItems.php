<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions;

use AdzerCMS\Contracts\Ui\Area;
use AdzerCMS\Contracts\Ui\Menu;
use AdzerCMS\Contracts\Ui\MenuItem;
use AdzerCMS\Extensions\Controller\AuthorController;

use function Symfony\Component\Translation\t;

final readonly class ExtensionsItems implements Menu
{
    public function items(Area $area): array
    {
        return Area::Admin === $area
            ? [new MenuItem('admin_extensions', t('extensions.menu'), 600, ['admin_extension'])]
            : [new MenuItem(AuthorController::LIST, t('extensions.mine.menu'), 500, [AuthorController::LIST])];
    }
}
