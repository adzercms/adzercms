<?php

declare(strict_types=1);

namespace AdzerCMS\Mailing;

use AdzerCMS\Contracts\Ui\Area;
use AdzerCMS\Contracts\Ui\Menu;
use AdzerCMS\Contracts\Ui\MenuItem;

use function Symfony\Component\Translation\t;

final readonly class MailingItems implements Menu
{
    public function items(Area $area): array
    {
        return Area::Admin === $area ? [new MenuItem('admin_notices', t('mailing.menu.notices'), 500, ['admin_notice', 'admin_broadcast'])] : [];
    }
}
