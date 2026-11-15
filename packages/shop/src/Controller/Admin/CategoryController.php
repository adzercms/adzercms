<?php

declare(strict_types=1);

namespace AdzerCMS\Shop\Controller\Admin;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Ui\Back;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Fragment;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Core\Ui\Table;
use AdzerCMS\Shop\Categories;
use AdzerCMS\Shop\Entity\Category;
use AdzerCMS\Shop\Products;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function Symfony\Component\Translation\t;

#[Route('/admin/shop/categories')]
#[IsGranted(Role::ADMIN)]
final class CategoryController extends AbstractController
{
    private const LIST = 'admin_shop_categories';

    public function __construct(private readonly Categories $categories, private readonly Products $products)
    {
    }

    #[Route('', name: self::LIST, methods: ['GET'])]
    public function index(Request $request): Response
    {
        $table = Table::of($request, self::LIST, [
            Column::of('name', t('shop.column.name'), 'c.name'),
            Column::of('products', t('shop.column.products'), 'products'),
            Column::plain('actions'),
        ], 'name', t('shop.category.empty'), order: Order::of('admin_shop_categories_order', 'c.position'));
        $context = ['table' => $table, 'rows' => $this->categories->listing($table)];

        if ($request->headers->has('HX-Request')) {
            return $this->render('@Shop/admin/_categories.html.twig', $context);
        }

        return $this->render('@Shop/admin/categories.html.twig', $context + ['counts' => $this->products->counts()]);
    }

    #[Route('/order', name: 'admin_shop_categories_order', methods: ['POST'])]
    public function order(Request $request): Response
    {
        $this->categories->arrange(Order::moved($request));

        return $this->index($request);
    }

    #[Route('/new', name: 'admin_shop_category_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        return $this->edit(null, $request);
    }

    #[Route('/{id}', name: 'admin_shop_category', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(Category $category, Request $request): Response
    {
        return $this->edit($category, $request);
    }

    #[Route('/{id}/drop', name: 'admin_shop_category_drop', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function drop(Category $category, Request $request): Response
    {
        $name = $category->getName();
        $refusal = $this->categories->remove($category);
        $this->addFlash(null === $refusal ? 'success' : 'error', $refusal->reason ?? t('shop.flash.category_dropped', ['name' => $name]));

        return $this->redirect(Back::of($request, $this->generateUrl(self::LIST)));
    }

    private function edit(?Category $category, Request $request): Response
    {
        $name = $category?->getName() ?? '';
        $error = null;

        if ($request->isMethod('POST')) {
            $name = $request->request->getString('name');
            $saved = $this->categories->save($category, $name);
            if ($saved instanceof Category) {
                $this->addFlash('success', t('shop.flash.category_saved', ['name' => $saved->getName()]));

                return Fragment::go(Back::of($request, $this->generateUrl(self::LIST)));
            }
            $error = $saved->reason;
        }

        return $this->render('@Shop/admin/_category.html.twig', ['category' => $category, 'name' => $name, 'error' => $error]);
    }
}
