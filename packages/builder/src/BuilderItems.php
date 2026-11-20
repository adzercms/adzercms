<?php

declare(strict_types=1);

namespace AdzerCMS\Builder;

use AdzerCMS\Builder\Controller\ProjectController;
use AdzerCMS\Contracts\Ui\Area;
use AdzerCMS\Contracts\Ui\Menu;
use AdzerCMS\Contracts\Ui\MenuItem;

use function Symfony\Component\Translation\t;

final readonly class BuilderItems implements Menu
{
    public function items(Area $area): array
    {
        return Area::Cabinet === $area ? [new MenuItem(ProjectController::LIST, t('builder.projects.menu'), 450, [ProjectController::PROJECT])] : [];
    }
}
