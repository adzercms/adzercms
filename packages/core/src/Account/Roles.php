<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Account;

use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Contracts\Ui\Area;
use AdzerCMS\Contracts\Ui\Menu;
use AdzerCMS\Core\Module\Bundles;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class Roles
{
    /** @param iterable<Menu> $menus */
    public function __construct(
        #[AutowireIterator(Menu::class)]
        private iterable $menus,
        private Bundles $bundles,
    ) {
    }

    public function chosen(): bool
    {
        foreach ($this->menus as $menu) {
            if (CoreKind::Feature === $this->bundles->owner($menu)::kind() && [] !== $menu->items(Area::Cabinet)) {
                return true;
            }
        }

        return false;
    }
}
