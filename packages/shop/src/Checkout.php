<?php

declare(strict_types=1);

namespace AdzerCMS\Shop;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Ui\Money;
use AdzerCMS\Shop\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

use function Symfony\Component\Translation\t;

final readonly class Checkout
{
    public const LIMITER = 'shop';
    public const LIMIT = 5;
    public const INTERVAL = '10 minutes';
    public const NAME = 'name';
    public const PHONE = 'phone';
    public const EMAIL = 'email';
    public const RECEIPT = 'receipt';
    public const POINT = 'point';
    public const ADDRESS = 'address';
    public const PAYMENT = 'payment';
    public const COMMENT = 'comment';
    public const TRAP = 'website';
    public const FORM = 'form';

    public function __construct(
        private Cart $cart,
        private ShopSettings $settings,
        private EntityManagerInterface $em,
        private RateLimiterFactoryInterface $shopLimiter,
    ) {
    }

    public function closed(): ?Refusal
    {
        return match (true) {
            [] === $this->settings->receipts() => new Refusal(t('shop.refusal.no_receipt')),
            [] === $this->settings->payments() => new Refusal(t('shop.refusal.no_payment')),
            default => null,
        };
    }

    /** @return Order|array<string, Refusal>|null */
    public function take(ParameterBag $input, string $address): Order|array|null
    {
        if ('' !== trim($input->getString(self::TRAP))) {
            return null;
        }

        $lines = $this->cart->lines();
        $closed = [] === $lines ? new Refusal(t('shop.refusal.empty')) : $this->closed();
        if (null !== $closed) {
            return [self::FORM => $closed];
        }

        $receipt = self::chosen($this->settings->receipts(), Receipt::tryFrom($input->getString(self::RECEIPT)));
        $payment = self::chosen($this->settings->payments(), PaymentMethod::tryFrom($input->getString(self::PAYMENT)));
        $points = $this->settings->points();
        $point = 1 === \count($points) ? $points[0] : array_find($points, static fn (PickupPoint $point): bool => $input->getString(self::POINT) === $point->key);
        $name = trim($input->getString(self::NAME));
        $phone = ContactKind::Phone->normalize(trim($input->getString(self::PHONE)));
        $email = trim($input->getString(self::EMAIL));
        $delivery = trim($input->getString(self::ADDRESS));
        $comment = trim($input->getString(self::COMMENT));

        $refusals = array_filter([
            self::NAME => '' === $name || mb_strlen($name) > Order::NAME ? new Refusal(t('shop.refusal.buyer', ['limit' => Order::NAME])) : null,
            self::PHONE => null === $phone ? new Refusal(t('shop.refusal.phone')) : null,
            self::EMAIL => '' === $email || null !== ContactKind::Email->normalize($email) ? null : new Refusal(t('shop.refusal.email')),
            self::RECEIPT => null === $receipt ? new Refusal(t('shop.refusal.receipt')) : null,
            self::POINT => Receipt::Pickup === $receipt && null === $point ? new Refusal(t('shop.refusal.point')) : null,
            self::ADDRESS => Receipt::Delivery === $receipt && ('' === $delivery || mb_strlen($delivery) > Order::ADDRESS) ? new Refusal(t('shop.refusal.address', ['limit' => Order::ADDRESS])) : null,
            self::PAYMENT => null === $payment ? new Refusal(t('shop.refusal.payment')) : null,
            self::COMMENT => mb_strlen($comment) > Order::COMMENT ? new Refusal(t('shop.refusal.comment', ['limit' => Order::COMMENT])) : null,
        ]);
        if ([] !== $refusals || null === $receipt || null === $payment || null === $phone) {
            return $refusals;
        }
        if (!$this->shopLimiter->create($address)->consume()->isAccepted()) {
            return [self::FORM => new Refusal(t('shop.refusal.often'))];
        }

        $pickup = Receipt::Pickup === $receipt ? $point : null;
        $price = Receipt::Delivery === $receipt ? $this->settings->deliveryPrice() : null;
        $order = new Order(
            $name,
            $phone,
            ContactKind::Email->normalize($email),
            $receipt,
            $pickup?->key,
            $pickup?->address,
            Receipt::Delivery === $receipt ? $delivery : null,
            '' === $comment ? null : $comment,
            $payment,
            $price,
            Money::add(Cart::total($lines), $price ?? '0'),
        );
        foreach ($lines as $line) {
            $order->hold($line->product, $line->product->getName(), $line->variant?->getName(), $line->price(), $line->quantity);
        }
        $this->em->persist($order);
        $this->em->flush();
        $this->cart->clear();

        return $order;
    }

    /**
     * @template T of \UnitEnum
     *
     * @param list<T> $offered
     * @param T|null  $asked
     *
     * @return T|null
     */
    private static function chosen(array $offered, ?\UnitEnum $asked): ?\UnitEnum
    {
        return 1 === \count($offered) ? $offered[0] : (\in_array($asked, $offered, true) ? $asked : null);
    }
}
