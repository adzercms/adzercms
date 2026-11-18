<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Controller\Admin;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Ui\Back;
use AdzerCMS\Core\Ui\Fragment;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Pages\Entity\MenuEntry;
use AdzerCMS\Pages\MenuForm;
use AdzerCMS\Pages\MenuRows;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function Symfony\Component\Translation\t;

#[Route('/admin/pages/menu')]
#[IsGranted(Role::ADMIN)]
final class MenuController extends AbstractController
{
    public const LIST = 'admin_pages_menu';
    public const ORDER = 'admin_pages_menu_order';

    public function __construct(
        private readonly MenuRows $rows,
        private readonly MenuForm $form,
    ) {
    }

    #[Route('', name: self::LIST, methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->render($request->headers->has('HX-Request') ? MenuRows::TEMPLATE : '@Pages/admin/menu.html.twig', $this->rows->listing($request));
    }

    #[Route('/order', name: self::ORDER, methods: ['POST'])]
    public function order(Request $request): Response
    {
        $this->form->arrange(Order::moved($request));

        return $this->render(MenuRows::TEMPLATE, $this->rows->listing($request));
    }

    #[Route('/new', name: 'admin_pages_menu_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        return $this->edit(null, $request);
    }

    #[Route('/{id}', name: 'admin_pages_menu_entry', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(MenuEntry $entry, Request $request): Response
    {
        return $this->edit($entry, $request);
    }

    #[Route('/{id}/drop', name: 'admin_pages_menu_drop', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function drop(MenuEntry $entry, Request $request): Response
    {
        $this->form->drop($entry);
        $this->addFlash('success', t('pages.flash.menu_dropped'));

        return $this->redirect(Back::of($request, $this->generateUrl(self::LIST)));
    }

    private function edit(?MenuEntry $entry, Request $request): Response
    {
        $posted = $request->isMethod('POST');
        $refusals = [];
        if ($posted) {
            $saved = $this->form->save($entry, $request->request);
            if ($saved instanceof MenuEntry) {
                $this->addFlash('success', t('pages.flash.menu_saved'));

                return Fragment::go(Back::of($request, $this->generateUrl(self::LIST)));
            }
            $refusals = $saved;
        }

        return $this->render('@Pages/admin/_menu_entry.html.twig', [
            'entry' => $entry,
            'choices' => $this->form->choices($entry),
            'target' => $posted ? $request->request->getString(MenuForm::TARGET) : MenuForm::target($entry),
            'label' => $posted ? $request->request->getString(MenuForm::LABEL) : $entry?->getLabel(),
            'address' => $posted ? $request->request->getString(MenuForm::ADDRESS) : $entry?->getAddress(),
            'refusals' => $refusals,
        ]);
    }
}
