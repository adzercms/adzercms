<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Account\Fixture\Lobby;

use AdzerCMS\Contracts\Ui\Area;
use AdzerCMS\Contracts\Ui\Menu;
use AdzerCMS\Contracts\Ui\MenuItem;

use function Symfony\Component\Translation\t;

final readonly class LobbyMenu implements Menu
{
    public function items(Area $area): array
    {
        return Area::Cabinet === $area ? [new MenuItem('lobby_profile', t('profile'), 900)] : [];
    }
}
