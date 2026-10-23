<?php

declare(strict_types=1);

namespace AdzerCMS\Shop\Entity;

use AdzerCMS\Shop\OrderStatus;
use AdzerCMS\Shop\PaymentMethod;
use AdzerCMS\Shop\Receipt;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use function Symfony\Component\Clock\now;

#[ORM\Entity]
#[ORM\Table(name: 'shop_order')]
#[ORM\UniqueConstraint(name: 'uniq_shop_order_key', columns: ['page_key'])]
#[ORM\Index(name: 'idx_shop_order_status_arrived', columns: ['status', 'arrived'])]
#[ORM\Index(name: 'idx_shop_order_arrived', columns: ['arrived'])]
#[ORM\Index(name: 'idx_shop_order_phone', columns: ['phone'])]
#[ORM\Index(name: 'idx_shop_order_email', columns: ['email'])]
#[ORM\Index(name: 'idx_shop_order_payment', columns: ['payment_id'])]
class Order
{
    public const KEY = 32;
    public const NAME = 190;
    public const PHONE = 32;
    public const EMAIL = 254;
    public const POINT = 64;
    public const ADDRESS = 255;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'page_key', length: self::KEY)]
    private string $key;

    #[ORM\Column(nullable: true)]
    private ?int $paymentId = null;

    #[ORM\Column]
    private bool $paid = false;

    #[ORM\Column(length: 16, enumType: OrderStatus::class)]
    private OrderStatus $status;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $arrived;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $staged;

    /** @var Collection<int, OrderItem> */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order', cascade: ['persist'])]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $items;

    public function __construct(
        #[ORM\Column(length: self::NAME)]
        private string $name,
        #[ORM\Column(length: self::PHONE)]
        private string $phone,
        #[ORM\Column(length: self::EMAIL, nullable: true)]
        private ?string $email,
        #[ORM\Column(length: 16, enumType: Receipt::class)]
        private Receipt $receipt,
        #[ORM\Column(length: self::POINT, nullable: true)]
        private ?string $pointKey,
        #[ORM\Column(length: self::ADDRESS, nullable: true)]
        private ?string $pointAddress,
        #[ORM\Column(length: self::ADDRESS, nullable: true)]
        private ?string $address,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $comment,
        #[ORM\Column(length: 16, enumType: PaymentMethod::class)]
        private PaymentMethod $payment,
        #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
        private ?string $deliveryPrice,
        #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
        private string $total,
    ) {
        $this->key = bin2hex(random_bytes(self::KEY / 2));
        $this->status = PaymentMethod::Online === $payment ? OrderStatus::AwaitingPayment : OrderStatus::New;
        $this->arrived = now();
        $this->staged = $this->arrived;
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getReceipt(): Receipt
    {
        return $this->receipt;
    }

    public function getPointKey(): ?string
    {
        return $this->pointKey;
    }

    public function getPointAddress(): ?string
    {
        return $this->pointAddress;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getPayment(): PaymentMethod
    {
        return $this->payment;
    }

    public function getDeliveryPrice(): ?string
    {
        return $this->deliveryPrice;
    }

    public function getTotal(): string
    {
        return $this->total;
    }

    public function getPaymentId(): ?int
    {
        return $this->paymentId;
    }

    public function isPaid(): bool
    {
        return $this->paid;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function getArrived(): \DateTimeImmutable
    {
        return $this->arrived;
    }

    public function getStaged(): \DateTimeImmutable
    {
        return $this->staged;
    }

    /** @return list<OrderItem> */
    public function getItems(): array
    {
        return array_values($this->items->toArray());
    }

    public function hold(?Product $product, string $name, ?string $variant, string $price, int $quantity): void
    {
        $this->items->add(new OrderItem($this, $product, $name, $variant, $price, $quantity));
    }
}
