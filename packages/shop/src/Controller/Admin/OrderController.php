<?php

declare(strict_types=1);

namespace AdzerCMS\Shop\Controller\Admin;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Shop\Entity\Order;
use AdzerCMS\Shop\Orders;
use AdzerCMS\Shop\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function Symfony\Component\Translation\t;

#[Route('/admin/shop/orders')]
#[IsGranted(Role::ADMIN)]
final class OrderController extends AbstractController
{
    public const STATUS = 'status';
    private const LIST = 'admin_shop_orders';
    private const ORDER = 'admin_shop_order';

    public function __construct(private readonly Orders $orders, private readonly EntityManagerInterface $em)
    {
    }

    #[Route('', name: self::LIST, methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->render($request->headers->has('HX-Request') ? '@Shop/admin/_orders.html.twig' : '@Shop/admin/orders.html.twig', $this->orders->listing($request, self::LIST));
    }

    #[Route('/{id}', name: self::ORDER, requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Order $order): Response
    {
        return $this->card($order, null);
    }

    #[Route('/{id}', name: 'admin_shop_order_stage', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function stage(Order $order, Request $request): Response
    {
        $status = OrderStatus::tryFrom($request->request->getString(self::STATUS));
        if (null === $status || !\in_array($status, OrderStatus::MANUAL, true)) {
            return $this->card($order, new Refusal(t('shop.refusal.status')));
        }
        $order->stage($status);
        $this->em->flush();
        $this->addFlash('success', t('shop.flash.staged', ['number' => $order->getId()]));

        return $this->redirectToRoute(self::ORDER, ['id' => $order->getId()], Response::HTTP_SEE_OTHER);
    }

    private function card(Order $order, ?Refusal $refusal): Response
    {
        return $this->render('@Shop/admin/order.html.twig', [
            'order' => $order,
            'contacts' => array_filter([[ContactKind::Phone, $order->getPhone()], [ContactKind::Email, $order->getEmail()]], static fn (array $contact): bool => null !== $contact[1]),
            'statuses' => OrderStatus::MANUAL,
            'refusal' => $refusal,
        ], new Response(status: null === $refusal ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
