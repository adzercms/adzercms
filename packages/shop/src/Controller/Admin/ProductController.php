<?php

declare(strict_types=1);

namespace AdzerCMS\Shop\Controller\Admin;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Setting\Toggle;
use AdzerCMS\Core\Ui\Back;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Core\Ui\Fragment;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Core\Ui\Table;
use AdzerCMS\Shop\Categories;
use AdzerCMS\Shop\Entity\Category;
use AdzerCMS\Shop\Entity\Product;
use AdzerCMS\Shop\Entity\Variant;
use AdzerCMS\Shop\Products;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function Symfony\Component\Translation\t;

#[Route('/admin/shop/products')]
#[IsGranted(Role::ADMIN)]
final class ProductController extends AbstractController
{
    private const LIST = 'admin_shop_products';
    private const BLANK = 3;

    public function __construct(private readonly Products $products, private readonly Categories $categories)
    {
    }

    #[Route('', name: self::LIST, methods: ['GET'])]
    public function index(Request $request): Response
    {
        $categories = [];
        foreach ($this->categories->all() as $category) {
            $categories[(string) $category->getId()] = $category->getName();
        }

        $table = Table::of($request, self::LIST, [
            Column::plain('photo', t('shop.column.photo')),
            Column::of('name', t('shop.column.name'), 'p.name'),
            Column::of('category', t('shop.column.category'), 'c.name')->aside(),
            Column::plain('price', t('shop.column.price')),
            Column::of('active', t('shop.column.active'), 'p.active'),
            Column::plain('actions'),
        ], 'name', t('shop.product.empty'), order: Order::of('admin_shop_products_order', 'c.position', 'c.id', 'p.position'), filters: [
            Filter::search(),
            Filter::select(Products::CATEGORY, t('shop.filter.category'), $categories),
        ]);
        $context = ['table' => $table, 'rows' => $this->products->listing($table)];

        if ($request->headers->has('HX-Request')) {
            return $this->render('@Shop/admin/_products.html.twig', $context);
        }

        return $this->render('@Shop/admin/products.html.twig', $context + ['counts' => $this->products->counts()]);
    }

    #[Route('/order', name: 'admin_shop_products_order', methods: ['POST'])]
    public function order(Request $request): Response
    {
        $this->products->arrange(Order::moved($request));

        return $this->index($request);
    }

    #[Route('/new', name: 'admin_shop_product_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        return $this->edit(null, $request);
    }

    #[Route('/{id}', name: 'admin_shop_product', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(Product $product, Request $request): Response
    {
        return $this->edit($product, $request);
    }

    #[Route('/{id}/drop', name: 'admin_shop_product_drop', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function drop(Product $product, Request $request): Response
    {
        $name = $product->getName();
        $this->products->remove($product);
        $this->addFlash('success', t('shop.flash.product_dropped', ['name' => $name]));

        return $this->redirect(Back::of($request, $this->generateUrl(self::LIST)));
    }

    private function edit(?Product $product, Request $request): Response
    {
        $categories = $this->categories->all();
        $form = [
            'category' => $product?->getCategory()->getId() ?? ($categories[0] ?? null)?->getId(),
            'name' => $product?->getName() ?? '',
            'description' => $product?->getDescription() ?? '',
            'price' => $product?->getPrice() ?? '',
            'variants' => array_map(
                static fn (Variant $variant): array => ['id' => (string) $variant->getId(), 'name' => $variant->getName(), 'price' => $variant->getPrice()],
                $product?->getVariants() ?? [],
            ),
            'active' => $product?->isActive() ?? true,
            'hidden' => [],
        ];
        $error = null;

        if ($request->isMethod('POST')) {
            $input = $request->request;
            $chosen = $input->getInt('category');
            $form = [
                'category' => $chosen,
                'name' => $input->getString('name'),
                'description' => $input->getString('description'),
                'price' => $input->getString('price'),
                'variants' => array_values(array_filter($input->all('variants'), static fn (mixed $row): bool => \is_array($row) && '' !== trim(implode('', array_filter($row, is_string(...)))))),
                'active' => Toggle::On === Toggle::tryFrom($input->getString('active')),
                'hidden' => array_map(intval(...), array_filter($input->all('hidden'), static fn (mixed $id): bool => \is_string($id) && ctype_digit($id))),
            ];
            $uploads = array_values(array_filter($request->files->all('photos'), static fn (mixed $file): bool => $file instanceof UploadedFile));
            $saved = $this->products->save(
                $product,
                array_find($categories, static fn (Category $category): bool => $category->getId() === $chosen),
                $form['name'],
                $form['description'],
                $form['price'],
                $form['variants'],
                $form['active'],
                $uploads,
                array_values($form['hidden']),
            );
            if ($saved instanceof Product) {
                $this->addFlash('success', t('shop.flash.product_saved', ['name' => $saved->getName()]));

                return Fragment::go(Back::of($request, $this->generateUrl(self::LIST)));
            }
            $error = $saved->reason;
        }

        return $this->render('@Shop/admin/_product.html.twig', [
            'product' => $product,
            'categories' => $categories,
            'form' => $form,
            'blank' => self::BLANK,
            'error' => $error,
        ]);
    }
}
