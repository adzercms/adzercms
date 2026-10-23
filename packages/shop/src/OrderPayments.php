<?php

declare(strict_types=1);

namespace AdzerCMS\Shop;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Ui\Money;
use AdzerCMS\Payments\Entity\Payment;
use AdzerCMS\Payments\PaymentConfirming;
use AdzerCMS\Payments\PaymentService;
use AdzerCMS\Payments\PaymentStatus;
use AdzerCMS\Shop\Controller\CheckoutController;
use AdzerCMS\Shop\Entity\Order;
use AdzerCMS\Shop\Entity\OrderItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

final readonly class OrderPayments
{
    public function __construct(
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $urls,
        private TranslatorInterface $translator,
        private ?PaymentService $acquiring = null,
    ) {
    }

    public function ready(): bool
    {
        return true === $this->acquiring?->ready();
    }

    public function below(string $amount): ?Refusal
    {
        $floor = $this->acquiring?->floor() ?? '0';

        return Money::compare($amount, $floor) < 0 ? new Refusal(t('shop.refusal.floor', ['floor' => Money::precise($floor)])) : null;
    }

    public function open(Order $order, string $method): Payment|Refusal
    {
        if (null === $this->acquiring) {
            return new Refusal(t('shop.refusal.online'));
        }

        $items = array_map(static fn (OrderItem $item): array => [
            'name' => null === $item->getVariant() ? $item->getName() : $item->getName().', '.$item->getVariant(),
            'quantity' => $item->getQuantity(),
            'price' => $item->getPrice(),
        ], $order->getItems());
        $delivery = $order->getDeliveryPrice();
        if (null !== $delivery) {
            $items[] = ['name' => $this->translator->trans('shop.cart.delivery'), 'quantity' => 1, 'price' => $delivery];
        }

        $payment = $this->acquiring->order(
            $items,
            $method,
            $this->translator->trans('shop.order.title', ['number' => $order->getId()]),
            $order->getPhone(),
            $order->getEmail(),
            $this->urls->generate(CheckoutController::ORDER, ['key' => $order->getKey()], UrlGeneratorInterface::ABSOLUTE_URL),
        );
        if ($payment instanceof Payment) {
            $order->bill((int) $payment->getId());
            $this->em->flush();
        }

        return $payment;
    }

    public function offer(Order $order): string|Refusal
    {
        if (null === $this->acquiring || !$this->acquiring->ready()) {
            return new Refusal(t('shop.refusal.online'));
        }

        $payment = null === $order->getPaymentId() ? null : $this->em->find(Payment::class, $order->getPaymentId());
        if (PaymentStatus::Pending !== $payment?->getStatus()) {
            $methods = $this->acquiring->methods();
            $payment = $this->open($order, null !== $payment && isset($methods[$payment->getMethod()]) ? $payment->getMethod() : (string) array_key_first($methods));
            if ($payment instanceof Refusal) {
                return $payment;
            }
        }

        return $this->urls->generate('payment_code', ['number' => $payment->getNumber()]);
    }

    #[AsEventListener]
    public function settle(PaymentConfirming $confirming): void
    {
        $this->em->getRepository(Order::class)->findOneBy(['paymentId' => $confirming->payment->getId()])?->settle();
    }
}
