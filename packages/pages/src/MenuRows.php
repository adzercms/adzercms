<?php

declare(strict_types=1);

namespace AdzerCMS\Pages;

use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Core\Ui\SiteNav;
use AdzerCMS\Core\Ui\Table;
use AdzerCMS\Pages\Controller\Admin\MenuController;
use AdzerCMS\Pages\Entity\MenuEntry;
use AdzerCMS\Pages\Repository\MenuEntryRepository;
use Symfony\Component\HttpFoundation\Request;

use function Symfony\Component\Translation\t;

final readonly class MenuRows
{
    public const TEMPLATE = '@Pages/admin/_menu.html.twig';

    public function __construct(
        private MenuEntryRepository $entries,
        private PageMenu $menu,
        private SiteNav $nav,
    ) {
    }

    /** @return array<string, mixed> */
    public function listing(Request $request): array
    {
        $table = Table::of($request, MenuController::LIST, [
            Column::plain(MenuForm::LABEL, t('pages.menu.label')),
            Column::plain(MenuForm::ADDRESS, t('pages.menu.address')),
            Column::plain('shown', t('pages.menu.shown')),
            Column::plain('actions'),
        ], MenuForm::LABEL, t('pages.menu.empty'), order: Order::of(MenuController::ORDER, 'm.position', 'm.id'));

        $sections = $this->nav->sections();
        $rows = array_map(function (MenuEntry $entry) use ($sections): array {
            $link = $this->menu->link($entry, $sections);
            $page = $entry->getPage();

            return [
                'id' => $entry->getId(),
                'label' => $link->label ?? $page?->menuLabel() ?? (string) $entry->getSection(),
                'address' => $link->url ?? $page?->getPath() ?? '',
                'shown' => null !== $link,
            ];
        }, $this->entries->ordered());

        return ['table' => $table, 'rows' => $table->rows($rows)];
    }
}
