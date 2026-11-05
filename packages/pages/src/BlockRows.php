<?php

declare(strict_types=1);

namespace AdzerCMS\Pages;

use AdzerCMS\Contracts\Ui\Badge;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Core\Ui\Table;
use AdzerCMS\Pages\Controller\Admin\BlockController;
use AdzerCMS\Pages\Controller\Admin\SiteController;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Entity\PageBlock;
use AdzerCMS\Pages\Repository\PageBlockRepository;
use Symfony\Component\HttpFoundation\Request;

use function Symfony\Component\Translation\t;

final readonly class BlockRows
{
    public const TEMPLATE = '@Pages/admin/_blocks.html.twig';

    private const ID = 'blocks';

    public function __construct(
        private PageBlockRepository $blocks,
        private Blocks $types,
    ) {
    }

    /** @return array<string, mixed> */
    public function listing(Page $page, Request $request): array
    {
        $table = Table::of($request, SiteController::PAGE, [
            Column::plain(BlockForm::TYPE, t('pages.column.type')),
            Column::plain('summary', t('pages.column.summary')),
            Column::plain('actions'),
        ], BlockForm::TYPE, t('pages.block.empty'), sticky: ['id' => $page->getId()], id: self::ID, order: Order::of(BlockController::ORDER, 'b.position', 'b.id'));

        $rows = array_map(function (PageBlock $block): array {
            $type = $this->types->find($block->getType());

            return ['id' => $block->getId(), 'block' => $block, 'type' => $type, 'summary' => null === $type ? '' : BlockForm::summary($type, $block)];
        }, $this->blocks->of($page));

        return [
            'table' => $table,
            'rows' => $table->rows($rows),
            'unavailable' => Badge::idle(t('pages.block.unavailable')),
            'heading' => t('pages.blocks'),
            'actions' => [['route' => BlockController::NEW, 'params' => ['id' => $page->getId()], 'label' => t('pages.block.add')]],
        ];
    }
}
