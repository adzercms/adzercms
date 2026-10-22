<?php

declare(strict_types=1);

namespace AdzerCMS\Leads;

use AdzerCMS\Contracts\Ui\Area;
use AdzerCMS\Contracts\Ui\Menu;
use AdzerCMS\Contracts\Ui\MenuItem;

use function Symfony\Component\Translation\t;

final readonly class LeadsItems implements Menu
{
    public function items(Area $area): array
    {
        return Area::Admin === $area ? [new MenuItem('admin_lead_stages', t('leads.menu'), 250, ['admin_lead'])] : [];
    }
}
