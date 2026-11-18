<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Tests\Fixture;

use AdzerCMS\Contracts\Ui\MenuItem;
use AdzerCMS\Contracts\Ui\SiteSections;
use AdzerCMS\Pages\Controller\Admin\SiteController;

use function Symfony\Component\Translation\t;

final class Lounge implements SiteSections
{
    public const ROUTE = SiteController::LIST;
    public const LABEL = 'Lounge';

    public function sections(): array
    {
        return [new MenuItem(self::ROUTE, t(self::LABEL), 100)];
    }
}
