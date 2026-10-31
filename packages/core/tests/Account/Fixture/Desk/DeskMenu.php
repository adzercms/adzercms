<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Account\Fixture\Desk;

use AdzerCMS\Contracts\Ui\Area;
use AdzerCMS\Contracts\Ui\Menu;
use AdzerCMS\Contracts\Ui\MenuItem;

use function Symfony\Component\Translation\t;

final readonly class DeskMenu implements Menu
{
    public function __construct(private bool $cabinet)
    {
    }

    public function items(Area $area): array
    {
        return Area::Admin === $area || $this->cabinet ? [new MenuItem('desk', t('desk'), 400)] : [];
    }
}
