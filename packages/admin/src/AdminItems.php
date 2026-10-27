<?php

declare(strict_types=1);

namespace AdzerCMS\Admin;

use AdzerCMS\Contracts\Ui\Area;
use AdzerCMS\Contracts\Ui\Menu;
use AdzerCMS\Contracts\Ui\MenuItem;

use function Symfony\Component\Translation\t;

final readonly class AdminItems implements Menu
{
    public function items(Area $area): array
    {
        return Area::Admin === $area ? [
            new MenuItem('admin_dashboard', t('admin.menu.dashboard'), 0),
            new MenuItem('admin_users', t('admin.menu.users'), 100, ['admin_user']),
            new MenuItem('admin_modules', t('admin.menu.modules'), 900, ['admin_module']),
            new MenuItem('admin_translations', t('admin.menu.translations'), 950, ['admin_translation']),
            new MenuItem('admin_settings', t('admin.menu.settings'), 1000, ['admin_setting']),
        ] : [];
    }
}
