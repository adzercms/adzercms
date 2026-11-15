<?php

declare(strict_types=1);

namespace AdzerCMS\Shop\Entity;

use AdzerCMS\Core\Ui\Money;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'shop_order_item')]
#[ORM\Index(name: 'idx_shop_order_item_order', columns: ['order_id'])]
#[ORM\Index(name: 'idx_shop_order_item_product', columns: ['product_id'])]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'items')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Order $order,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?Product $product,
        #[ORM\Column(length: Product::NAME)]
        private string $name,
        #[ORM\Column(length: Variant::NAME, nullable: true)]
        private ?string $variant,
        #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
        private string $price,
        #[ORM\Column]
        private int $quantity,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getVariant(): ?string
    {
        return $this->variant;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function sum(): string
    {
        return Money::multiply($this->price, $this->quantity);
    }
}
