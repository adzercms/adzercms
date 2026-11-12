<?php

declare(strict_types=1);

namespace AdzerCMS\Admin;

use AdzerCMS\Contracts\Ui\Area;
use AdzerCMS\Contracts\Ui\Menu;
use AdzerCMS\Contracts\Ui\MenuItem;
use AdzerCMS\Core\Security\ModuleVoter;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

use function Symfony\Component\Translation\t;

final readonly class AdminItems implements Menu
{
    public function __construct(private AuthorizationCheckerInterface $authorization)
    {
    }

    public function items(Area $area): array
    {
        return Area::Admin === $area ? [
            new MenuItem('admin_dashboard', t('admin.menu.dashboard'), 0),
            new MenuItem('admin_users', t('admin.menu.users'), 100, ['admin_user']),
            ...$this->authorization->isGranted(ModuleVoter::MANAGE) ? [new MenuItem('admin_modules', t('admin.menu.modules'), 900, ['admin_module'])] : [],
            new MenuItem('admin_references', t('admin.menu.references'), 950, ['admin_reference_']),
            new MenuItem('admin_settings', t('admin.menu.settings'), 1000, ['admin_setting']),
        ] : [];
    }
}
