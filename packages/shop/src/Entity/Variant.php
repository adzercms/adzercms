<?php

declare(strict_types=1);

namespace AdzerCMS\Shop\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'shop_variant')]
#[ORM\Index(name: 'idx_shop_variant_product', columns: ['product_id'])]
class Variant
{
    public const int NAME = 64;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'variants')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Product $product,
        #[ORM\Column(length: self::NAME)]
        private string $name,
        #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
        private string $price,
        #[ORM\Column]
        private int $position,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function change(string $name, string $price, int $position): void
    {
        $this->name = $name;
        $this->price = $price;
        $this->position = $position;
    }
}
