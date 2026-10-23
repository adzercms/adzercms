<?php

declare(strict_types=1);

namespace AdzerCMS\Shop\Controller;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Ui\Back;
use AdzerCMS\Shop\Cart;
use AdzerCMS\Shop\Products;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function Symfony\Component\Translation\t;

#[Route('/shop')]
final class StorefrontController extends AbstractController
{
    public const PAGE = 'shop';
    public const PRODUCT = 'shop_product';
    private const CARD = 'card';

    public function __construct(
        private readonly Products $products,
        private readonly Cart $cart,
    ) {
    }

    #[Route('', name: self::PAGE, methods: ['GET'])]
    public function shop(): Response
    {
        $products = $this->products->active();
        $groups = [];
        foreach ($products as $product) {
            $category = $product->getCategory();
            $groups[(int) $category->getId()] ??= ['category' => $category, 'products' => []];
            $groups[(int) $category->getId()]['products'][] = $product;
        }

        return $this->render('@Shop/shop.html.twig', ['groups' => array_values($groups), 'products' => $products]);
    }

    #[Route('/{id}', name: self::PRODUCT, requirements: ['id' => '\d+'], methods: ['GET'])]
    public function product(int $id): Response
    {
        return $this->render('@Shop/product.html.twig', ['product' => $this->products->active([$id])[0] ?? throw $this->createNotFoundException()]);
    }

    #[Route('/cart', name: 'shop_cart_add', methods: ['POST'])]
    public function add(Request $request): Response
    {
        $input = $request->request;
        $line = $this->cart->add($input->getInt('product'), $input->has('variant') ? $input->getInt('variant') : null, $input->getString('quantity', '1'));
        $this->addFlash(...$line instanceof Refusal ? ['error', $line->reason] : ['success', t('shop.flash.added', ['name' => $line->name()])]);

        $back = Back::of($request, $this->generateUrl(self::PAGE));

        return $this->redirect($input->has(self::CARD) ? $back.'#product-'.$input->getInt('product') : $back, Response::HTTP_SEE_OTHER);
    }

    #[Route('/cart/line', name: 'shop_cart_change', methods: ['POST'])]
    public function change(Request $request): Response
    {
        $line = $this->cart->change($request->request->getString('line'), $request->request->getString('quantity'));
        if (null !== $line) {
            $this->addFlash(...match (true) {
                $line instanceof Refusal => ['error', $line->reason],
                0 === $line->quantity => ['success', t('shop.flash.removed', ['name' => $line->name()])],
                default => ['success', t('shop.flash.changed', ['name' => $line->name(), 'quantity' => $line->quantity])],
            });
        }

        return $this->redirect(Back::of($request, $this->generateUrl(CheckoutController::CART)), Response::HTTP_SEE_OTHER);
    }
}
