<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Controller\Admin;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Ui\Fragment;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Pages\Block;
use AdzerCMS\Pages\BlockForm;
use AdzerCMS\Pages\BlockRows;
use AdzerCMS\Pages\Blocks;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Entity\PageBlock;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function Symfony\Component\Translation\t;

#[Route('/admin/pages')]
#[IsGranted(Role::ADMIN)]
final class BlockController extends AbstractController
{
    public const NEW = 'admin_page_block_new';
    public const ORDER = 'admin_page_blocks_order';

    private const DIALOG = '@Pages/admin/_block.html.twig';

    public function __construct(
        private readonly Blocks $types,
        private readonly BlockForm $form,
        private readonly BlockRows $rows,
    ) {
    }

    #[Route('/{id}/blocks/order', name: self::ORDER, requirements: ['id' => '\d+'], methods: ['POST'])]
    public function order(Page $page, Request $request): Response
    {
        $this->form->arrange($page, Order::moved($request));

        return $this->render(BlockRows::TEMPLATE, $this->rows->listing($page, $request));
    }

    #[Route('/{id}/blocks/new', name: self::NEW, requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function create(Page $page, Request $request): Response
    {
        $types = $this->types->all();
        $posted = $request->isMethod('POST');
        $type = $types[($posted ? $request->request : $request->query)->getString(BlockForm::TYPE)] ?? reset($types);
        if (!$type instanceof Block) {
            throw $this->createNotFoundException();
        }

        $refusals = [];
        if ($posted) {
            $outcome = $this->form->add($page, $type, $request);
            if ($outcome instanceof PageBlock) {
                $this->addFlash('success', t('pages.flash.block_added', ['type' => $type->label()]));

                return Fragment::go($this->card($page));
            }
            $refusals = $outcome;
        }

        return $this->dialog($page, null, $type, $types, $refusals, $posted ? $request->request->all(BlockForm::VALUE) : []);
    }

    #[Route('/blocks/{id}', name: 'admin_page_block', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(PageBlock $block, Request $request): Response
    {
        $type = $this->types->find($block->getType()) ?? throw $this->createNotFoundException();

        $refusals = [];
        if ($request->isMethod('POST')) {
            $refusals = $this->form->fill($block, $type, $request);
            if ([] === $refusals) {
                $this->addFlash('success', t('pages.flash.block_saved', ['type' => $type->label()]));

                return Fragment::go($this->card($block->getPage()));
            }
        }

        return $this->dialog($block->getPage(), $block, $type, [], $refusals, $request->isMethod('POST') ? $request->request->all(BlockForm::VALUE) : $block->getValues());
    }

    #[Route('/blocks/{id}/drop', name: 'admin_page_block_drop', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function drop(PageBlock $block): Response
    {
        $page = $block->getPage();
        $this->form->drop($block);
        $this->addFlash('success', t('pages.flash.block_dropped'));

        return $this->redirect($this->card($page));
    }

    /**
     * @param array<string, Block> $types
     * @param list<Refusal>        $refusals
     * @param array<string, mixed> $values
     */
    private function dialog(Page $page, ?PageBlock $block, Block $type, array $types, array $refusals, array $values): Response
    {
        return $this->render(self::DIALOG, [
            'page' => $page,
            'block' => $block,
            'type' => $type,
            'types' => $types,
            'values' => BlockForm::shown($type, $values, $block),
            'refusals' => $refusals,
        ]);
    }

    private function card(Page $page): string
    {
        return $this->generateUrl(SiteController::PAGE, ['id' => $page->getId()]);
    }
}
