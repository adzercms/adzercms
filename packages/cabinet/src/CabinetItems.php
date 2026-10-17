<?php

declare(strict_types=1);

namespace AdzerCMS\Cabinet;

use AdzerCMS\Contracts\Ui\Area;
use AdzerCMS\Contracts\Ui\Menu;
use AdzerCMS\Contracts\Ui\MenuItem;
use AdzerCMS\Contracts\Ui\Tabs;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Ui\PageTabs;
use Symfony\Bundle\SecurityBundle\Security;

use function Symfony\Component\Translation\t;

final readonly class CabinetItems implements Menu
{
    private const LOGOUT = '_logout_main';

    public function __construct(
        private PageTabs $pages,
        private Security $security,
    ) {
    }

    public function items(Area $area): array
    {
        if (Area::Cabinet !== $area) {
            return [];
        }

        $user = $this->security->getUser();
        $home = $user instanceof User && [] !== $this->pages->of(Tabs::CABINET_HOME, $user->getId());

        return [
            ...$home ? [new MenuItem('cabinet_account', t('cabinet.menu.home'), 0, icon: 'home')] : [],
            new MenuItem('cabinet_profile', t('cabinet.menu.profile'), 900, ['cabinet_profile'], t('cabinet.menu.account'), icon: 'user'),
            new MenuItem(self::LOGOUT, t('cabinet.menu.logout'), 1000, [], t('cabinet.menu.account')),
        ];
    }
}
