<?php

declare(strict_types=1);

namespace AdzerCMS\Support;

use AdzerCMS\Contracts\Ui\Area;
use AdzerCMS\Contracts\Ui\Menu;
use AdzerCMS\Contracts\Ui\MenuItem;
use AdzerCMS\Core\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

use function Symfony\Component\Translation\t;

final readonly class SupportItems implements Menu
{
    public function __construct(
        private Tickets $tickets,
        private SupportSettings $support,
        private Security $security,
    ) {
    }

    public function items(Area $area): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        if (Area::Admin === $area) {
            return [new MenuItem('support_tickets', t('support.menu.tickets'), 300, ['support_'], count: $this->tickets->waiting(null))];
        }

        $group = t('support.menu.group');

        return [
            new MenuItem('support_tickets', t('support.menu.tickets'), 700, ['support_'], $group, $this->tickets->waiting($user), icon: 'chat'),
            ...$this->support->helpEnabled() ? [new MenuItem('help_window', t('support.menu.help'), 710, [], $group, dialog: true)] : [],
        ];
    }
}
