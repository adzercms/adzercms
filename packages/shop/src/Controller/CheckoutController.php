<?php

declare(strict_types=1);

namespace AdzerCMS\Shop\Controller;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Ui\Money;
use AdzerCMS\Shop\Cart;
use AdzerCMS\Shop\Checkout;
use AdzerCMS\Shop\Entity\Order;
use AdzerCMS\Shop\OrderPayments;
use AdzerCMS\Shop\OrderStatus;
use AdzerCMS\Shop\PaymentMethod;
use AdzerCMS\Shop\ShopSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function Symfony\Component\Translation\t;

#[Route('/shop')]
final class CheckoutController extends AbstractController
{
    public const CART = 'shop_cart';
    public const ORDER = 'shop_order';

    public function __construct(
        private readonly Cart $cart,
        private readonly Checkout $checkout,
        private readonly ShopSettings $settings,
        private readonly OrderPayments $paying,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/cart', name: self::CART, methods: ['GET'])]
    public function cart(): Response
    {
        return $this->page([], []);
    }

    #[Route('/order', name: 'shop_order_take', methods: ['POST'])]
    public function take(Request $request): Response
    {
        $order = $this->checkout->take($request->request, (string) $request->getClientIp());
        if (\is_array($order)) {
            return $this->page($request->request->all(), $order, Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (PaymentMethod::Online === $order?->getPayment()) {
            return $this->offer($order);
        }

        $this->addFlash('success', t('shop.flash.ordered'));

        return $this->redirectToRoute(null === $order ? StorefrontController::PAGE : self::ORDER, null === $order ? [] : ['key' => $order->getKey()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/order/{key}', name: self::ORDER, requirements: ['key' => '[0-9a-f]{32}'], methods: ['GET'])]
    public function order(string $key): Response
    {
        $order = $this->found($key);

        return $this->render('@Shop/order.html.twig', [
            'order' => $order,
            'payable' => OrderStatus::AwaitingPayment === $order->getStatus() && $this->paying->ready(),
        ]);
    }

    #[Route('/order/{key}/pay', name: 'shop_order_pay', requirements: ['key' => '[0-9a-f]{32}'], methods: ['POST'])]
    public function pay(string $key): Response
    {
        $order = $this->found($key);

        return OrderStatus::AwaitingPayment === $order->getStatus()
            ? $this->offer($order)
            : $this->redirectToRoute(self::ORDER, ['key' => $key], Response::HTTP_SEE_OTHER);
    }

    private function offer(Order $order): Response
    {
        $offer = $this->paying->offer($order);
        if ($offer instanceof Refusal) {
            $this->addFlash('error', $offer->reason);

            return $this->redirectToRoute(self::ORDER, ['key' => $order->getKey()], Response::HTTP_SEE_OTHER);
        }

        return $this->redirect($offer, Response::HTTP_SEE_OTHER);
    }

    private function found(string $key): Order
    {
        return $this->em->getRepository(Order::class)->findOneBy(['key' => $key]) ?? throw $this->createNotFoundException();
    }

    /**
     * @param array<string, mixed>   $input
     * @param array<string, Refusal> $refusals
     */
    private function page(array $input, array $refusals, int $status = Response::HTTP_OK): Response
    {
        $lines = $this->cart->lines();
        $total = Cart::total($lines);
        $delivery = $this->settings->deliveryPrice();

        return $this->render('@Shop/cart.html.twig', [
            'lines' => $lines,
            'total' => $total,
            'charged' => null === $delivery ? null : Money::add($total, $delivery),
            'closed' => $this->checkout->closed(),
            'receipts' => $this->settings->receipts(),
            'payments' => $this->settings->payments(),
            'points' => $this->settings->points(),
            'delivery' => $delivery,
            'input' => $input,
            'refusals' => $refusals,
        ], new Response(status: $status));
    }
}
